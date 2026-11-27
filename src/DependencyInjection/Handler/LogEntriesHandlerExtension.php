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

use Monolog\Handler\LogEntriesHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class LogEntriesHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'logentries';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('token')->end()
                ->booleanNode('use_ssl')->defaultTrue()->end()
                ->scalarNode('timeout')->end()
                ->scalarNode('connection_timeout')->end()
            ->end()
            ->example([
                'token' => 'example token',
                'use_ssl' => true,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'logentries' === $v['type'] && empty($v['logentries']['token'] ?? null); })
                ->thenInvalid('The token has to be specified to use a LogEntriesHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     token: string|null,
     *     use_ssl: bool,
     *     timeout?: mixed,
     *     connection_timeout?: mixed,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(LogEntriesHandler::class);
        $definition->setArguments([
            $handler['token'],
            $handler['use_ssl'],
            $config['level'],
            $config['bubble'],
        ]);
        if (isset($handler['timeout'])) {
            $definition->addMethodCall('setTimeout', [$handler['timeout']]);
        }
        if (isset($handler['connection_timeout'])) {
            $definition->addMethodCall('setConnectionTimeout', [$handler['connection_timeout']]);
        }

        return $definition;
    }
}
