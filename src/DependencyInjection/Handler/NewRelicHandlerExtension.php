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

use Monolog\Handler\NewRelicHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class NewRelicHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'newrelic';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('app_name')->defaultNull()->end()
            ->end()
            ->example([
                'app_name' => null,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        // No specific validation
    }

    /**
     * @param array{
     *     app_name: string|null,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(NewRelicHandler::class);
        $definition->setArguments([
            $config['level'],
            $config['bubble'],
            $handler['app_name'],
        ]);

        return $definition;
    }
}
