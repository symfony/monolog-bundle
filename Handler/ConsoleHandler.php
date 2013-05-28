<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

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
     * The stream to use when the console OutputInterface is not a StreamOutput.
     */
    const FALLBACK_STREAM = 'php://output';

    /**
     * The error stream to use when the console OutputInterface is not a StreamOutput.
     */
    const FALLBACK_ERROR_STREAM = 'php://stderr';

    private $enabled = false;
    private $stream;
    private $streamCreated = false;
    private $errorStream;
    private $errorStreamCreated = false;

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
        return $this->enabled && parent::isHandling($record);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if (!$this->enabled) {
            return false;
        }

        return parent::handle($record);
    }

    /**
     * Sets the console output to use for printing logs.
     *
     * It enabled the writing unless the output verbosity is set to quiet.
     *
     * @param OutputInterface $output The console output to use
     */
    public function setOutput(OutputInterface $output)
    {
        // close streams that might have been created before
        $this->close();

        if (OutputInterface::VERBOSITY_QUIET === $output->getVerbosity()) {
            return; // the handler remains disabled
        }

        if ($output instanceof StreamOutput) {
            $this->stream = $output->getStream();
        }

        if ($output instanceof ConsoleOutputInterface && $output->getErrorOutput() instanceof StreamOutput) {
            $this->errorStream = $output->getErrorOutput()->getStream();
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

        $this->enabled = true;
    }

    /**
     * Disables the output and closes newly created streams.
     *
     * It does not close streams coming from StreamOutput because they belong to the console.
     */
    public function close()
    {
        if ($this->streamCreated && is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
        $this->streamCreated = false;

        if ($this->errorStreamCreated && is_resource($this->errorStream)) {
            fclose($this->errorStream);
        }
        $this->errorStream = null;
        $this->errorStreamCreated = false;

        $this->enabled = false;

        parent::close();
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        if (null === $this->stream) {
            $errorMessage = null;
            set_error_handler(function ($code, $msg) use (&$errorMessage) {
                $errorMessage = preg_replace('{^fopen\(.*?\): }', '', $msg);
            });
            $this->stream = fopen(self::FALLBACK_STREAM, 'a');
            restore_error_handler();
            if (!is_resource($this->stream)) {
                $this->stream = null;
                $this->streamCreated = false;
                throw new \UnexpectedValueException(sprintf('The stream "%s" could not be opened: '.$errorMessage, self::FALLBACK_STREAM));
            }
            $this->streamCreated = true;
        }

        if (null === $this->errorStream) {
            $errorMessage = null;
            set_error_handler(function ($code, $msg) use (&$errorMessage) {
                $errorMessage = preg_replace('{^fopen\(.*?\): }', '', $msg);
            });
            $this->errorStream = fopen(self::FALLBACK_ERROR_STREAM, 'a');
            restore_error_handler();
            if (!is_resource($this->errorStream)) {
                $this->errorStream = null;
                $this->errorStreamCreated = false;
                throw new \UnexpectedValueException(sprintf('The stream "%s" could not be opened: '.$errorMessage, self::FALLBACK_STREAM));
            }
            $this->errorStreamCreated = true;
        }

        if ($record['level'] >= Logger::ERROR) {
            fwrite($this->errorStream, (string) $record['formatted']);
        } else {
            fwrite($this->stream, (string) $record['formatted']);
        }
    }
}
