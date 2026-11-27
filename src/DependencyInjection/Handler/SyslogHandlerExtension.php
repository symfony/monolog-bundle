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

use Monolog\Handler\SyslogHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class SyslogHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'syslog';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('ident')->defaultValue('php')->end()
                ->scalarNode('facility')->defaultValue('user')->end()
                ->scalarNode('logopts')->defaultValue(\LOG_PID)->end()
            ->end()
            ->example([
                'ident' => 'php',
                'facility' => 'user',
                'logopts' => \LOG_PID,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        // No specific validation
    }

    /**
     * @param array{
     *     ident: string,
     *     facility: string,
     *     logopts: int,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(SyslogHandler::class);
        $definition->setArguments([
            $handler['ident'],
            $handler['facility'],
            $config['level'],
            $config['bubble'],
            $handler['logopts'],
        ]);

        return $definition;
    }
}
