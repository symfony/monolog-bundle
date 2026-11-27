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

use Symfony\Bridge\Monolog\Handler\ServerLogHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class ServerLogHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'server_log';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('host')->end()
            ->end()
            ->example([
                'host' => '127.0.0.1:9911',
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'server_log' === $v['type'] && empty($v['server_log']['host'] ?? null); })
                ->thenInvalid('The host has to be specified to use a ServerLogHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     host: string|null,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(ServerLogHandler::class);
        $definition->setArguments([
            $handler['host'],
            $config['level'],
            $config['bubble'],
        ]);

        return $definition;
    }
}
