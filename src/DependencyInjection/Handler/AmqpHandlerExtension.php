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

use Monolog\Handler\AmqpHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AmqpHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'amqp';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('exchange')->end()
                ->scalarNode('exchange_name')->defaultValue('log')->end()
            ->end()
            ->example([
                'exchange' => 'exchange.service_id',
                'exchange_name' => 'log',
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'amqp' === $v['type'] && empty($v['amqp']['exchange'] ?? null); })
                ->thenInvalid('The exchange has to be specified to use a AmqpHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     exchange: string|null,
     *     exchange_name: string,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(AmqpHandler::class);
        $definition->setArguments([
            new Reference($handler['exchange']),
            $handler['exchange_name'],
            $config['level'],
            $config['bubble'],
        ]);

        return $definition;
    }
}
