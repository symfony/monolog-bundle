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

use MongoDB\Client;
use Monolog\Formatter\MongoDBFormatter;
use Monolog\Handler\MongoDBHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class MongoDBHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'mongodb';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->beforeNormalization()
                ->ifString()
                ->then(function ($v) { return ['id' => $v]; })
            ->end()
            ->children()
                ->scalarNode('id')
                    ->info('ID of a MongoDB\Client service')
                    ->example('doctrine_mongodb.odm.logs_connection')
                ->end()
                ->scalarNode('uri')->end()
                ->scalarNode('username')->end()
                ->scalarNode('password')->end()
                ->scalarNode('database')->defaultValue('monolog')->end()
                ->scalarNode('collection')->defaultValue('logs')->end()
            ->end()
            ->validate()
                ->ifTrue(function ($v) {
                    return !isset($v['id']) && !isset($v['uri']);
                })
                ->thenInvalid('The "mongodb" handler configuration requires either a service "id" or a connection "uri".')
            ->end()
            ->example([
                'uri' => 'mongodb://localhost:27017',
                'database' => 'monolog',
                'collection' => 'logs',
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'mongodb' === $v['type'] && !isset($v['mongodb']); })
                ->thenInvalid('The "mongodb" configuration has to be specified to use a "mongodb" handler type.')
            ->end()
        ;
    }

    /**
     * @param array{
     *     id?: string,
     *     uri?: string,
     *     username?: string,
     *     password?: string,
     *     database: string,
     *     collection: string,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(MongoDBHandler::class);

        if (!class_exists(Client::class)) {
            throw new \RuntimeException('The "mongodb" handler requires the mongodb/mongodb package to be installed.');
        }

        if (isset($handler['id'])) {
            $client = new Reference($handler['id']);
        } else {
            $uriOptions = ['appname' => 'monolog-bundle'];

            if (isset($handler['username'])) {
                $uriOptions['username'] = $handler['username'];
            }

            if (isset($handler['password'])) {
                $uriOptions['password'] = $handler['password'];
            }

            $client = new Definition(Client::class, [
                $handler['uri'],
                $uriOptions,
            ]);
        }

        $definition->setArguments([
            $client,
            $handler['database'],
            $handler['collection'],
            $config['level'],
            $config['bubble'],
        ]);

        if (empty($config['formatter'])) {
            $formatter = new Definition(MongoDBFormatter::class);
            $definition->addMethodCall('setFormatter', [$formatter]);
        }

        return $definition;
    }
}
