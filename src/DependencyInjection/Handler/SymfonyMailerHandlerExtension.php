<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection\Handler;

use Symfony\Bridge\Monolog\Handler\MailerHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class SymfonyMailerHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'symfony_mailer';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('from_email')->end()
                ->arrayNode('to_email')
                    ->prototype('scalar')->end()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static function ($v) { return [$v]; })
                    ->end()
                ->end()
                ->scalarNode('subject')->end()
                ->scalarNode('content_type')->defaultNull()->end()
                ->scalarNode('mailer')->defaultNull()->end()
                ->arrayNode('email_prototype')
                    ->canBeUnset()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static function ($v) { return ['id' => $v]; })
                    ->end()
                    ->children()
                        ->scalarNode('id')->isRequired()->end()
                        ->scalarNode('method')->defaultNull()->end()
                    ->end()
                ->end()
            ->end()
            ->example([
                'from_email' => 'from@example.com',
                'to_email' => 'to@example.com',
                'subject' => 'subject',
                'mailer' => 'mailer.mailer',
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) {
                    return 'symfony_mailer' === $v['type']
                        && empty($v['symfony_mailer']['email_prototype'] ?? null)
                        && (empty($v['symfony_mailer']['from_email'] ?? null) || empty($v['symfony_mailer']['to_email'] ?? null) || empty($v['symfony_mailer']['subject'] ?? null));
                })
                ->thenInvalid('The sender, recipient and subject or an email prototype have to be specified to use the Symfony MailerHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     from_email?: string|list<string>,
     *     to_email?: list<string>,
     *     subject?: string|list<string>,
     *     mailer: string|null,
     *     email_prototype?: array{id: string, method: string|null},
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $mailer = $handler['mailer'] ?: 'mailer.mailer';
        if (isset($handler['email_prototype'])) {
            if (!empty($handler['email_prototype']['method'])) {
                $prototype = [new Reference($handler['email_prototype']['id']), $handler['email_prototype']['method']];
            } else {
                $prototype = new Reference($handler['email_prototype']['id']);
            }
        } else {
            $prototype = (new Definition(\Symfony\Component\Mime\Email::class))
                ->setPublic(false)
                ->addMethodCall('from', [$handler['from_email']])
                ->addMethodCall('to', $handler['to_email'])
                ->addMethodCall('subject', [$handler['subject']]);
        }

        $definition = new Definition(MailerHandler::class);
        $definition->setArguments([
            new Reference($mailer),
            $prototype,
            $config['level'],
            $config['bubble'],
        ]);

        return $definition;
    }
}
