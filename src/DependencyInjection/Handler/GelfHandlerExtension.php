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

use Monolog\Handler\GelfHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class GelfHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'gelf';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->arrayNode('publisher')
                    ->canBeUnset()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static function ($v) { return ['id' => $v]; })
                    ->end()
                    ->children()
                        ->scalarNode('id')->end()
                        ->scalarNode('hostname')->end()
                        ->scalarNode('port')->defaultValue(12201)->end()
                        ->scalarNode('chunk_size')->defaultValue(1420)->end()
                        ->enumNode('encoder')->values(['json', 'compressed_json'])->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static function ($v) {
                            return !isset($v['id']) && !isset($v['hostname']);
                        })
                        ->thenInvalid('What must be set is either the hostname or the id.')
                    ->end()
                ->end()
            ->end()
            ->example([
                'publisher' => [
                    'hostname' => 'localhost',
                    'port' => 12201,
                    'chunk_size' => 1420,
                ],
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'gelf' === $v['type'] && !isset($v['gelf']['publisher']); })
                ->thenInvalid('The publisher has to be specified to use a GelfHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     publisher: array{
     *         id?: string,
     *         hostname?: string,
     *         port: int,
     *         chunk_size: int,
     *         encoder?: 'json'|'compressed_json',
     *     }|string,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        if (isset($handler['publisher']['id'])) {
            $publisher = new Reference($handler['publisher']['id']);
        } elseif (class_exists(\Gelf\Transport\UdpTransport::class)) {
            $transport = new Definition(\Gelf\Transport\UdpTransport::class, [
                $handler['publisher']['hostname'],
                $handler['publisher']['port'],
                $handler['publisher']['chunk_size'],
            ]);
            $transport->setPublic(false);

            if (isset($handler['publisher']['encoder'])) {
                if ('compressed_json' === $handler['publisher']['encoder']) {
                    $encoderClass = \Gelf\Encoder\CompressedJsonEncoder::class;
                } elseif ('json' === $handler['publisher']['encoder']) {
                    $encoderClass = \Gelf\Encoder\JsonEncoder::class;
                } else {
                    throw new \RuntimeException('The gelf message encoder must be either "compressed_json" or "json".');
                }

                $encoder = new Definition($encoderClass);
                $encoder->setPublic(false);

                $transport->addMethodCall('setMessageEncoder', [$encoder]);
            }

            $publisher = new Definition(\Gelf\Publisher::class, []);
            $publisher->addMethodCall('addTransport', [$transport]);
            $publisher->setPublic(false);
        } else {
            throw new \RuntimeException('The gelf handler requires the graylog2/gelf-php package to be installed.');
        }

        $definition = new Definition(GelfHandler::class);
        $definition->setArguments([
            $publisher,
            $config['level'],
            $config['bubble'],
        ]);

        return $definition;
    }
}
