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

use Monolog\Handler\HandlerInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Handlers grouping several nested handlers (group, whatfailuregroup, fallbackgroup).
 */
final class GroupHandlerExtension implements HandlerExtensionInterface
{
    /**
     * @param non-empty-string              $name
     * @param class-string<HandlerInterface> $handlerClass
     */
    public function __construct(
        private string $name,
        private string $handlerClass,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->arrayNode('members')
                    ->canBeUnset()
                    ->performNoDeepMerging()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->example([
                'members' => ['handler_a', 'handler_b'],
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        // No specific validation
    }

    /**
     * @param array{
     *     members: list<string>,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $references = [];
        foreach ($handler['members'] as $nestedHandler) {
            if ($context->isHandlerDisabled($nestedHandler)) {
                // a disabled handler is not registered as a service, skip it
                continue;
            }
            $nestedHandlerId = $context->getHandlerId($nestedHandler);
            $context->markNestedHandler($nestedHandlerId);
            $references[] = new Reference($nestedHandlerId);
        }

        $definition = new Definition($this->handlerClass);
        $definition->setArguments([
            $references,
            $config['bubble'],
        ]);

        return $definition;
    }
}
