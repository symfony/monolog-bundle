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

use Monolog\Handler\ErrorLogHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

class ErrorLogHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'error_log';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('message_type')->defaultValue(0)->end()
                ->booleanNode('expand_newlines')->defaultFalse()->end()
            ->end()
            ->example([
                'message_type' => 0,
                'expand_newlines' => false,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        // No specific validation
    }

    /**
     * @param array{
     *     message_type: int,
     *     expand_newlines: bool,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(ErrorLogHandler::class);
        $definition->setArguments([
            $handler['message_type'],
            $config['level'],
            $config['bubble'],
            $handler['expand_newlines'],
        ]);

        return $definition;
    }
}
