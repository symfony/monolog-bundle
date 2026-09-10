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

use Monolog\Handler\RotatingFileHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class RotatingFileHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'rotating_file';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('path')->defaultValue('%kernel.logs_dir%/%kernel.environment%.log')->end()
                ->scalarNode('max_files')->defaultValue(0)->end()
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
                ->scalarNode('filename_format')->defaultValue('{filename}-{date}')->end()
                ->scalarNode('date_format')->defaultValue('Y-m-d')->end()
            ->end()
            ->example([
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'max_files' => 0,
                'file_permission' => null,
                'use_locking' => false,
                'filename_format' => '{filename}-{date}',
                'date_format' => 'Y-m-d',
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
     *     max_files: int,
     *     file_permission: int|null,
     *     use_locking: bool,
     *     filename_format: string,
     *     date_format: string,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(RotatingFileHandler::class);
        $definition->setArguments([
            $handler['path'],
            $handler['max_files'],
            $config['level'],
            $config['bubble'],
            $handler['file_permission'],
            $handler['use_locking'],
        ]);
        $definition->addMethodCall('setFilenameFormat', [
            $handler['filename_format'],
            $handler['date_format'],
        ]);

        return $definition;
    }
}
