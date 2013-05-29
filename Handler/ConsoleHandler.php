<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Bundle\MonologBundle\Formatter\ConsoleFormatter;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes to the console output depending on its verbosity setting.
 *
 * It is disabled by default and gets activated as soon as a command is executed.
 * Instead of listening to the console events, setOutput can also be called manually.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class ConsoleHandler extends AbstractProcessingHandler implements EventSubscriberInterface
{
    /**
     * @var OutputInterface|null
     */
    protected $output;

    /**
     * Constructor.
     *
     * The minimum logging level it is set based on the output verbosity in setOutput.
     *
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($bubble = true)
    {
        parent::__construct(Logger::DEBUG, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record)
    {
        return $this->updateLevel() && parent::isHandling($record);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        // we have to update the logging level each time because the verbosity of the
        // console output might have changed in the meantime (it is not immutable)
        return $this->updateLevel() && parent::handle($record);
    }

    /**
     * Sets the console output to use for printing logs.
     *
     * @param OutputInterface $output The console output to use
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Disables the output.
     */
    public function close()
    {
        $this->output = null;

        parent::close();
    }

    /**
     * Before a command is executed, the handler gets activated and the console output
     * is set in order to know where to write the logs.
     *
     * @param ConsoleCommandEvent $event
     */
    public function onCommand(ConsoleCommandEvent $event)
    {
        $this->setOutput($event->getOutput());
    }

    /**
     * After a command has been executed, it disables the output.
     *
     * @param ConsoleTerminateEvent $event
     */
    public function onTerminate(ConsoleTerminateEvent $event)
    {
        $this->close();
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

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        if ($record['level'] >= Logger::ERROR && $this->output instanceof ConsoleOutputInterface) {
            $this->output->getErrorOutput()->write((string) $record['formatted']);
        } else {
            $this->output->write((string) $record['formatted']);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultFormatter()
    {
        return new ConsoleFormatter();
    }

    /**
     * Updates the logging level based on the verbosity setting of the console output.
     *
     * @return Boolean Whether the handler is enabled and verbosity is not set to quiet.
     */
    private function updateLevel()
    {
        if (null === $this->output || OutputInterface::VERBOSITY_QUIET === $this->output->getVerbosity()) {
            return false;
        }

        switch ($this->output->getVerbosity()) {
            case OutputInterface::VERBOSITY_NORMAL:
                $this->setLevel(Logger::WARNING);
                break;
            case OutputInterface::VERBOSITY_VERBOSE:
                $this->setLevel(Logger::NOTICE);
                break;
            case OutputInterface::VERBOSITY_VERY_VERBOSE:
                $this->setLevel(Logger::INFO);
                break;
            default:
                $this->setLevel(Logger::DEBUG);
                break;
        }

        return true;
    }
}
