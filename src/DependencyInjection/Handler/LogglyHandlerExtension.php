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

use Monolog\Handler\LogglyHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Definition;

class LogglyHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'loggly';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('token')->end()
                ->arrayNode('tags')
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static function ($v) { return explode(',', $v); })
                    ->end()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($v) { return array_filter(array_map('trim', $v)); })
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->example([
                'token' => 'example token',
                'tags' => ['foo', 'bar'],
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'loggly' === $v['type'] && empty($v['loggly']['token'] ?? null); })
                ->thenInvalid('The token has to be specified to use a LogglyHandler')
            ->end()
            ->validate()
                ->ifTrue(static function ($v) { return 'loggly' === $v['type'] && !empty($v['loggly']['tags'] ?? []); })
                ->then(static function ($v) {
                    $invalidTags = preg_grep('/^[a-z0-9][a-z0-9\.\-_]*$/i', $v['loggly']['tags'], \PREG_GREP_INVERT);
                    if (!empty($invalidTags)) {
                        throw new InvalidConfigurationException(\sprintf('The following Loggly tags are invalid: "%s".', implode('", "', $invalidTags)));
                    }

                    return $v;
                })
            ->end()
        ;
    }

    /**
     * @param array{
     *     token: string|null,
     *     tags?: list<string>,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(LogglyHandler::class);
        $definition->setArguments([
            $handler['token'],
            $config['level'],
            $config['bubble'],
        ]);
        if (!empty($handler['tags'])) {
            $definition->addMethodCall('setTag', [implode(',', $handler['tags'])]);
        }

        return $definition;
    }
}
