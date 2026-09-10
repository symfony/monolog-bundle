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

use Monolog\Handler\FilterHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class FilterHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'filter';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('handler')->end()
                ->arrayNode('accepted_levels')
                    ->canBeUnset()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('min_level')->defaultValue('DEBUG')->end()
                ->scalarNode('max_level')->defaultValue('EMERGENCY')->end()
            ->end()
            ->example([
                'handler' => 'nested',
                'accepted_levels' => ['debug', 'info'],
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'filter' === $v['type'] && empty($v['filter']['handler'] ?? null); })
                ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler, BufferHandler, FilterHandler, DeduplicationHandler or SamplingHandler')
            ->end()
            ->validate()
                ->ifTrue(static function ($v) { return 'filter' === $v['type'] && 'DEBUG' !== ($v['filter']['min_level'] ?? 'DEBUG') && !empty($v['filter']['accepted_levels'] ?? []); })
                ->thenInvalid('You can not use min_level together with accepted_levels in a FilterHandler')
            ->end()
            ->validate()
                ->ifTrue(static function ($v) { return 'filter' === $v['type'] && 'EMERGENCY' !== ($v['filter']['max_level'] ?? 'EMERGENCY') && !empty($v['filter']['accepted_levels'] ?? []); })
                ->thenInvalid('You can not use max_level together with accepted_levels in a FilterHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     handler: string|null,
     *     accepted_levels?: list<string|int>,
     *     min_level: string|int,
     *     max_level: string|int,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $nestedHandlerId = $context->getHandlerId($handler['handler']);
        $context->markNestedHandler($nestedHandlerId);
        $minLevelOrList = !empty($handler['accepted_levels']) ? $handler['accepted_levels'] : $handler['min_level'];

        $definition = new Definition(FilterHandler::class);
        $definition->setArguments([
            new Reference($nestedHandlerId),
            $minLevelOrList,
            $handler['max_level'],
            $config['bubble'],
        ]);

        return $definition;
    }
}
