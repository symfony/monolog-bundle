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

use Monolog\Handler\NativeMailerHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class NativeMailerHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'native_mailer';
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
                ->arrayNode('headers')
                    ->canBeUnset()
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('parameters')
                    ->canBeUnset()
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(static function ($v) { return false !== strpbrk($v, "\r\n"); })
                            ->thenInvalid('Mailer parameters can not contain newline characters for security reasons.')
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->example([
                'from_email' => 'from@example.com',
                'to_email' => 'to@example.com',
                'subject' => 'subject',
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'native_mailer' === $v['type'] && (empty($v['native_mailer']['from_email'] ?? null) || empty($v['native_mailer']['to_email'] ?? null) || empty($v['native_mailer']['subject'] ?? null)); })
                ->thenInvalid('The sender, recipient and subject have to be specified to use a NativeMailerHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     from_email: string|list<string>|null,
     *     to_email: list<string>|null,
     *     subject: string|list<string>|null,
     *     headers?: list<string>,
     *     parameters?: list<string>,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(NativeMailerHandler::class);
        $definition->setArguments([
            $handler['to_email'],
            $handler['subject'],
            $handler['from_email'],
            $config['level'],
            $config['bubble'],
        ]);
        if (!empty($handler['headers'])) {
            $definition->addMethodCall('addHeader', [$handler['headers']]);
        }
        if (!empty($handler['parameters'])) {
            $definition->addMethodCall('addParameter', [$handler['parameters']]);
        }

        return $definition;
    }
}
