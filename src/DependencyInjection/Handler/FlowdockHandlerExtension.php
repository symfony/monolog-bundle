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

use Monolog\Formatter\FlowdockFormatter;
use Monolog\Handler\FlowdockHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class FlowdockHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'flowdock';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('token')->end()
                ->scalarNode('source')->end()
                ->scalarNode('from_email')->end()
            ->end()
            ->example([
                'token' => 'example token',
                'source' => 'app',
                'from_email' => 'app@example.com',
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'flowdock' === $v['type'] && empty($v['flowdock']['token'] ?? null); })
                ->thenInvalid('The token has to be specified to use a FlowdockHandler')
            ->end()
            ->validate()
                ->ifTrue(static function ($v) { return 'flowdock' === $v['type'] && empty($v['flowdock']['from_email'] ?? null); })
                ->thenInvalid('The from_email has to be specified to use a FlowdockHandler')
            ->end()
            ->validate()
                ->ifTrue(static function ($v) { return 'flowdock' === $v['type'] && empty($v['flowdock']['source'] ?? null); })
                ->thenInvalid('The source has to be specified to use a FlowdockHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     token: string|null,
     *     source: string|null,
     *     from_email: string|null,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(FlowdockHandler::class);
        $definition->setArguments([
            $handler['token'],
            $config['level'],
            $config['bubble'],
        ]);

        if (empty($config['formatter'])) {
            $formatter = new Definition(FlowdockFormatter::class, [
                $handler['source'],
                $handler['from_email'],
            ]);
            $formatterId = 'monolog.flowdock.formatter.'.sha1($handler['source'].'|'.$handler['from_email']);
            $formatter->setPublic(false);
            $context->container->setDefinition($formatterId, $formatter);

            $definition->addMethodCall('setFormatter', [new Reference($formatterId)]);
        }

        return $definition;
    }
}
