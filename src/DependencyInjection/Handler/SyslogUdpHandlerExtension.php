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

use Monolog\Handler\SyslogUdpHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class SyslogUdpHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'syslogudp';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('host')->end()
                ->scalarNode('port')->defaultValue(514)->end()
                ->scalarNode('facility')->defaultValue('user')->end()
                ->scalarNode('ident')->defaultValue('php')->end()
                ->scalarNode('rfc')->defaultValue(SyslogUdpHandler::RFC5424)->end()
            ->end()
            ->example([
                'host' => '127.0.0.1',
                'port' => 514,
                'facility' => 'user',
                'ident' => 'php',
                'rfc' => SyslogUdpHandler::RFC5424,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'syslogudp' === $v['type'] && empty($v['syslogudp']['host'] ?? null); })
                ->thenInvalid('The host has to be specified to use a syslogudp as handler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     host: string|null,
     *     port: int,
     *     facility: string,
     *     ident: string,
     *     rfc: int,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(SyslogUdpHandler::class);
        $definition->setArguments([
            $handler['host'],
            $handler['port'],
            $handler['facility'],
            $config['level'],
            $config['bubble'],
            $handler['ident'] ?: 'php',
            $handler['rfc'],
        ]);

        return $definition;
    }
}
