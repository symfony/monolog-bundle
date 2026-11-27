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

use Monolog\Handler\SlackWebhookHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class SlackWebhookHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'slackwebhook';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('webhook_url')->end()
                ->scalarNode('channel')->end()
                ->scalarNode('bot_name')->defaultValue('Monolog')->end()
                ->scalarNode('icon_emoji')->defaultNull()->end()
                ->scalarNode('use_attachment')->defaultTrue()->end()
                ->scalarNode('use_short_attachment')->defaultFalse()->end()
                ->scalarNode('include_extra')->defaultFalse()->end()
                ->arrayNode('exclude_fields')
                    ->canBeUnset()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->example([
                'webhook_url' => 'https://hooks.slack.com/services/...',
                'channel' => '#logs',
                'bot_name' => 'Monolog',
                'icon_emoji' => null,
                'use_attachment' => true,
                'use_short_attachment' => false,
                'include_extra' => false,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'slackwebhook' === $v['type'] && empty($v['slackwebhook']['webhook_url'] ?? null); })
                ->thenInvalid('The webhook_url have to be specified to use a SlackWebhookHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     webhook_url: string|null,
     *     channel: string|null,
     *     bot_name: string,
     *     icon_emoji: string|null,
     *     use_attachment: bool,
     *     use_short_attachment: bool,
     *     include_extra: bool,
     *     exclude_fields: list<string>,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(SlackWebhookHandler::class);
        $definition->setArguments([
            $handler['webhook_url'],
            $handler['channel'],
            $handler['bot_name'],
            $handler['use_attachment'],
            $handler['icon_emoji'],
            $handler['use_short_attachment'],
            $handler['include_extra'],
            $config['level'],
            $config['bubble'],
            $handler['exclude_fields'],
        ]);

        return $definition;
    }
}
