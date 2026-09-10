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

use Monolog\Handler\PushoverHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class PushoverHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'pushover';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('token')->end()
                ->variableNode('user')
                    ->validate()
                        ->ifTrue(static function ($v) {
                            return !\is_string($v) && !\is_array($v);
                        })
                        ->thenInvalid('User must be a string or an array.')
                    ->end()
                ->end()
                ->scalarNode('title')->defaultNull()->end()
                ->scalarNode('timeout')->end()
                ->scalarNode('connection_timeout')->end()
            ->end()
            ->example([
                'token' => 'pushover api token',
                'user' => 'user id or array of ids',
                'title' => null,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'pushover' === $v['type'] && (empty($v['pushover']['token'] ?? null) || empty($v['pushover']['user'] ?? null)); })
                ->thenInvalid('The token and user have to be specified to use a PushoverHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     token: string|null,
     *     user: string|list<string>|null,
     *     title: string|null,
     *     timeout?: mixed,
     *     connection_timeout?: mixed,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(PushoverHandler::class);
        $definition->setArguments([
            $handler['token'],
            $handler['user'],
            $handler['title'],
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
