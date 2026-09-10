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

use Monolog\Handler\SlackHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class SlackHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'slack';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('token')->end()
                ->scalarNode('channel')->end()
                ->scalarNode('bot_name')->defaultValue('Monolog')->end()
                ->scalarNode('use_attachment')->defaultTrue()->end()
                ->scalarNode('icon_emoji')->defaultNull()->end()
                ->scalarNode('use_short_attachment')->defaultFalse()->end()
                ->scalarNode('include_extra')->defaultFalse()->end()
                ->arrayNode('exclude_fields')
                    ->canBeUnset()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('timeout')->end()
                ->scalarNode('connection_timeout')->end()
            ->end()
            ->example([
                'token' => 'slack api token',
                'channel' => '#logs',
                'bot_name' => 'Monolog',
                'use_attachment' => true,
                'icon_emoji' => null,
                'use_short_attachment' => false,
                'include_extra' => false,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'slack' === $v['type'] && (empty($v['slack']['token'] ?? null) || empty($v['slack']['channel'] ?? null)); })
                ->thenInvalid('The token and channel have to be specified to use a SlackHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     token: string|null,
     *     channel: string|null,
     *     bot_name: string,
     *     use_attachment: bool,
     *     icon_emoji: string|null,
     *     use_short_attachment: bool,
     *     include_extra: bool,
     *     exclude_fields: list<string>,
     *     timeout?: mixed,
     *     connection_timeout?: mixed,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(SlackHandler::class);
        $definition->setArguments([
            $handler['token'],
            $handler['channel'],
            $handler['bot_name'],
            $handler['use_attachment'],
            $handler['icon_emoji'],
            $config['level'],
            $config['bubble'],
            $handler['use_short_attachment'],
            $handler['include_extra'],
            $handler['exclude_fields'],
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
