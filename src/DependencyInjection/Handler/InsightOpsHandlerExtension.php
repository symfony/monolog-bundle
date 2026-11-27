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

use Monolog\Handler\InsightOpsHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class InsightOpsHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'insightops';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('token')->end()
                ->scalarNode('region')->end()
                ->booleanNode('use_ssl')->defaultTrue()->end()
            ->end()
            ->example([
                'token' => 'example token',
                'region' => null,
                'use_ssl' => true,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'insightops' === $v['type'] && empty($v['insightops']['token'] ?? null); })
                ->thenInvalid('The token has to be specified to use a InsightOpsHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     token: string|null,
     *     region?: string|null,
     *     use_ssl: bool,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(InsightOpsHandler::class);
        $definition->setArguments([
            $handler['token'],
            $handler['region'] ?: 'us',
            $handler['use_ssl'],
            $config['level'],
            $config['bubble'],
        ]);

        return $definition;
    }
}
