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

use Monolog\Handler\CubeHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class CubeHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'cube';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('url')->end()
            ->end()
            ->example([
                'url' => 'udp://127.0.0.1:1180',
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'cube' === $v['type'] && empty($v['cube']['url'] ?? null); })
                ->thenInvalid('The url has to be specified to use a CubeHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     url: string|null,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(CubeHandler::class);
        $definition->setArguments([
            $handler['url'],
            $config['level'],
            $config['bubble'],
        ]);

        return $definition;
    }
}
