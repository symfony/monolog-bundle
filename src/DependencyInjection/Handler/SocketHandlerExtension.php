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

use Monolog\Handler\SocketHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class SocketHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'socket';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('connection_string')->end()
                ->scalarNode('timeout')->end()
                ->scalarNode('connection_timeout')->end()
                ->booleanNode('persistent')->end()
            ->end()
            ->example([
                'connection_string' => 'localhost:50505',
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'socket' === $v['type'] && empty($v['socket']['connection_string'] ?? null); })
                ->thenInvalid('The connection_string has to be specified to use a SocketHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     connection_string: string|null,
     *     timeout?: mixed,
     *     connection_timeout?: mixed,
     *     persistent?: bool,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(SocketHandler::class);
        $definition->setArguments([
            $handler['connection_string'],
            $config['level'],
            $config['bubble'],
        ]);
        if (isset($handler['timeout'])) {
            $definition->addMethodCall('setTimeout', [$handler['timeout']]);
        }
        if (isset($handler['connection_timeout'])) {
            $definition->addMethodCall('setConnectionTimeout', [$handler['connection_timeout']]);
        }
        if (isset($handler['persistent'])) {
            $definition->addMethodCall('setPersistent', [$handler['persistent']]);
        }

        return $definition;
    }
}
