<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle;

use Monolog\Handler\HandlerInterface;

/**
 * Closes all registered handlers when the kernel shuts down.
 *
 * The handlers are injected as an iterator of weak references, so only handlers
 * that have already been instantiated are closed. Unused handlers are not
 * instantiated just to be closed.
 *
 * @internal
 */
class HandlerLifecycleManager
{
    /**
     * @var iterable<HandlerInterface>
     */
    private iterable $handlers;

    /**
     * @param iterable<HandlerInterface> $handlers
     */
    public function __construct(iterable $handlers)
    {
        $this->handlers = $handlers;
    }

    public function close(): void
    {
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }
}
