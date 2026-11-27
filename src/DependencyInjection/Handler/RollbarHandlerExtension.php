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

use Monolog\Handler\RollbarHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RollbarHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'rollbar';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('id')->end()
                ->scalarNode('token')->end()
                ->arrayNode('config')
                    ->canBeUnset()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->example([
                'token' => 'rollbar api token',
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'rollbar' === $v['type'] && !empty($v['rollbar']['id'] ?? null) && !empty($v['rollbar']['token'] ?? null); })
                ->thenInvalid('You can not use both an id and a token in a RollbarHandler')
            ->end()
            ->validate()
                ->ifTrue(static function ($v) { return 'rollbar' === $v['type'] && empty($v['rollbar']['id'] ?? null) && empty($v['rollbar']['token'] ?? null); })
                ->thenInvalid('The id or the token has to be specified to use a RollbarHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     id?: string,
     *     token?: string,
     *     config: array<string, mixed>,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        if (!empty($handler['id'])) {
            $rollbarId = $handler['id'];
        } else {
            $rollbarConfig = $handler['config'] ?: [];
            $rollbarConfig['access_token'] = $handler['token'];
            $rollbar = new Definition(\Rollbar\RollbarLogger::class, [
                $rollbarConfig,
            ]);
            $rollbarId = 'monolog.rollbar.notifier.'.sha1(json_encode($rollbarConfig));
            $rollbar->setPublic(false);
            $context->container->setDefinition($rollbarId, $rollbar);
        }

        $definition = new Definition(RollbarHandler::class);
        $definition->setArguments([
            new Reference($rollbarId),
            $config['level'],
            $config['bubble'],
        ]);

        return $definition;
    }
}
