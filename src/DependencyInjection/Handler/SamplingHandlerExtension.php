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

use Monolog\Handler\SamplingHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class SamplingHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'sampling';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('handler')->end()
                ->integerNode('factor')->defaultValue(1)->min(1)->end()
            ->end()
            ->example([
                'handler' => 'nested',
                'factor' => 10,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'sampling' === $v['type'] && empty($v['sampling']['handler'] ?? null); })
                ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler, BufferHandler, FilterHandler, DeduplicationHandler or SamplingHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     handler: string|null,
     *     factor: int,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $nestedHandlerId = $context->getHandlerId($handler['handler']);
        $context->markNestedHandler($nestedHandlerId);

        $definition = new Definition(SamplingHandler::class);
        $definition->setArguments([
            new Reference($nestedHandlerId),
            $handler['factor'],
        ]);

        return $definition;
    }
}
