<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\EventListener;

use Symfony\Bundle\MonologBundle\Handler\ConsoleHandler;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A listener, executed before command execution, that sets the console output
 * on the console handlers so they know where to write the logs.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class LogToConsoleListener implements EventSubscriberInterface
{
    private $consoleHandlers;

    /**
     * Constructor.
     *
     * @param ConsoleHandler[] $consoleHandlers Array of ConsoleHandler instances
     */
    public function __construct(array $consoleHandlers)
    {
        $this->consoleHandlers = $consoleHandlers;
    }

    /**
     * Enables the handlers by setting the console output on them before a command is executed.
     *
     * @param ConsoleCommandEvent $event
     */
    public function onCommand(ConsoleCommandEvent $event)
    {
        foreach ($this->consoleHandlers as $handler) {
            /** @var $handler ConsoleHandler */
            $handler->setOutput($event->getOutput());
        }
    }

    /**
     * Disables the handlers after a command has been executed.
     *
     * @param ConsoleTerminateEvent $event
     */
    public function onTerminate(ConsoleTerminateEvent $event)
    {
        foreach ($this->consoleHandlers as $handler) {
            /** @var $handler ConsoleHandler */
            $handler->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            ConsoleEvents::COMMAND => 'onCommand',
            ConsoleEvents::TERMINATE => 'onTerminate'
        );
    }
}
