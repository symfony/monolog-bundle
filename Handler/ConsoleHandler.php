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
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes to the console output depending on its verbosity setting.
 *
 * It is disabled by default and gets activated when setOutput is called.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class ConsoleHandler extends AbstractProcessingHandler
{
    /**
     * @var OutputInterface|null
     */
    private $output;

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
        return null !== $this->output && parent::isHandling($record);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if (null === $this->output) {
            return false;
        }

        return parent::handle($record);
    }

    /**
     * Sets the console output to use for printing logs.
     *
     * It enables the writing unless the output verbosity is set to quiet.
     *
     * @param OutputInterface $output The console output to use
     */
    public function setOutput(OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_QUIET === $output->getVerbosity()) {
            $this->close();

            return;
        }

        switch ($output->getVerbosity()) {
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
}
