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

use Monolog\Handler\AbstractHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Handlers listening on the "kernel.response" event (firephp, chromephp).
 */
final class KernelResponseHandlerExtension implements HandlerExtensionInterface
{
    /**
     * @param non-empty-string              $name
     * @param class-string<AbstractHandler> $handlerClass
     */
    public function __construct(
        private string $name,
        private string $handlerClass,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        // No specific configuration
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        // No specific validation
    }

    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition($this->handlerClass);
        $definition->setArguments([
            $config['level'],
            $config['bubble'],
        ]);
        $definition->addTag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);

        return $definition;
    }
}
