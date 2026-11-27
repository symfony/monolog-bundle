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

use Monolog\Handler\BufferHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class BufferHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'buffer';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('handler')->end()
                ->scalarNode('buffer_size')->defaultValue(0)->end()
                ->booleanNode('flush_on_overflow')->defaultFalse()->end()
            ->end()
            ->example([
                'handler' => 'nested',
                'buffer_size' => 0,
                'flush_on_overflow' => false,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'buffer' === $v['type'] && empty($v['buffer']['handler'] ?? null); })
                ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler, BufferHandler, FilterHandler, DeduplicationHandler or SamplingHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     handler: string|null,
     *     buffer_size: int,
     *     flush_on_overflow: bool,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $nestedHandlerId = $context->getHandlerId($handler['handler']);
        $context->markNestedHandler($nestedHandlerId);

        $definition = new Definition(BufferHandler::class);
        $definition->setArguments([
            new Reference($nestedHandlerId),
            $handler['buffer_size'],
            $config['level'],
            $config['bubble'],
            $handler['flush_on_overflow'],
        ]);

        return $definition;
    }
}
