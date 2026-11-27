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

use Monolog\Handler\TelegramBotHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class TelegramBotHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'telegram';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('token')->end()
                ->scalarNode('channel')->defaultNull()->end()
                ->scalarNode('parse_mode')->defaultNull()->end()
                ->booleanNode('disable_webpage_preview')->defaultNull()->end()
                ->booleanNode('disable_notification')->defaultNull()->end()
                ->booleanNode('split_long_messages')->defaultFalse()->end()
                ->booleanNode('delay_between_messages')->defaultFalse()->end()
                ->integerNode('topic')->defaultNull()->end()
            ->end()
            ->example([
                'token' => 'bot token',
                'channel' => '-100',
                'parse_mode' => null,
                'disable_webpage_preview' => null,
                'disable_notification' => null,
                'split_long_messages' => false,
                'delay_between_messages' => false,
                'topic' => null,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'telegram' === $v['type'] && (empty($v['telegram']['token'] ?? null) || empty($v['telegram']['channel'] ?? null)); })
                ->thenInvalid('The token and channel have to be specified to use a TelegramBotHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     token: string|null,
     *     channel: string|null,
     *     parse_mode: string|null,
     *     disable_webpage_preview: bool|null,
     *     disable_notification: bool|null,
     *     split_long_messages: bool,
     *     delay_between_messages: bool,
     *     topic: int|null,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(TelegramBotHandler::class);
        $definition->setArguments([
            $handler['token'],
            $handler['channel'],
            $config['level'],
            $config['bubble'],
            $handler['parse_mode'],
            $handler['disable_webpage_preview'],
            $handler['disable_notification'],
            $handler['split_long_messages'],
            $handler['delay_between_messages'],
            $handler['topic'],
        ]);

        return $definition;
    }
}
