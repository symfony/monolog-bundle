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

use Monolog\Handler\DeduplicationHandler;
use Monolog\Level;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DeduplicationHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'deduplication';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('handler')->end()
                ->scalarNode('store')->defaultNull()->end()
                ->scalarNode('deduplication_level')->defaultValue(Level::Error->value)->end()
                ->scalarNode('time')->defaultValue(60)->end()
            ->end()
            ->example([
                'handler' => 'nested',
                'store' => null,
                'deduplication_level' => Level::Error->value,
                'time' => 60,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'deduplication' === $v['type'] && empty($v['deduplication']['handler'] ?? null); })
                ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler, BufferHandler, FilterHandler, DeduplicationHandler or SamplingHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     handler: string|null,
     *     store: string|null,
     *     deduplication_level: string|int,
     *     time: int,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $nestedHandlerId = $context->getHandlerId($handler['handler']);
        $context->markNestedHandler($nestedHandlerId);
        $defaultStore = '%kernel.cache_dir%/monolog_dedup_'.sha1($context->handlerId);

        $definition = new Definition(DeduplicationHandler::class);
        $definition->setArguments([
            new Reference($nestedHandlerId),
            $handler['store'] ?? $defaultStore,
            $handler['deduplication_level'],
            $handler['time'],
            $config['bubble'],
        ]);

        return $definition;
    }
}
