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

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Context passed to handler extensions when building a handler definition.
 *
 * @internal
 */
final class HandlerContext
{
    /**
     * @param \Closure(string): void $markNestedHandler
     * @param \Closure(string): bool $isHandlerDisabled
     */
    public function __construct(
        public readonly ContainerBuilder $container,
        public readonly string $handlerId,
        public readonly string $handlerType,
        private readonly \Closure $markNestedHandler,
        private readonly \Closure $isHandlerDisabled,
    ) {
    }

    /**
     * Returns the service id of a handler service by its configuration name.
     */
    public function getHandlerId(string $name): string
    {
        return \sprintf('monolog.handler.%s', $name);
    }

    /**
     * Marks a handler as nested so it is never pushed on loggers directly.
     */
    public function markNestedHandler(string $handlerId): void
    {
        ($this->markNestedHandler)($handlerId);
    }

    /**
     * Whether the given handler is disabled in the current configuration.
     */
    public function isHandlerDisabled(string $name): bool
    {
        return ($this->isHandlerDisabled)($name);
    }
}
