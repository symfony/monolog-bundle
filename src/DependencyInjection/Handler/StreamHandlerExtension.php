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

use Monolog\Handler\StreamHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class StreamHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'stream';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('path')->defaultValue('%kernel.logs_dir%/%kernel.environment%.log')->end()
                ->scalarNode('file_permission')
                    ->defaultNull()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static function ($v) {
                            if (str_starts_with($v, '0')) {
                                return octdec($v);
                            }

                            return (int) $v;
                        })
                    ->end()
                ->end()
                ->booleanNode('use_locking')->defaultFalse()->end()
            ->end()
            ->example([
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'file_permission' => null,
                'use_locking' => false,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        // No specific validation
    }

    /**
     * @param array{
     *     path: string,
     *     file_permission: int|null,
     *     use_locking: bool,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(StreamHandler::class);
        $definition->setArguments([
            $handler['path'],
            $config['level'],
            $config['bubble'],
            $handler['file_permission'],
            $handler['use_locking'],
        ]);

        return $definition;
    }
}
