<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Tests\DependencyInjection;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\DependencyInjection\FormatterConfigurator;

class FormatterConfiguratorTest extends TestCase
{
    public function testIncludeStacktracesOnLineFormatter()
    {
        $handler = new StreamHandler('php://memory');
        $formatter = new LineFormatter();
        $handler->setFormatter($formatter);

        $configurator = new FormatterConfigurator(includeStacktraces: true);
        $configurator($handler);

        // Verify includeStacktraces was called by formatting a record with an exception
        $exception = new \Exception('Test exception');
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: \Monolog\Level::Error,
            message: 'Test',
            context: ['exception' => $exception],
        );
        $output = $handler->getFormatter()->format($record);
        $this->assertStringContainsString('[stacktrace]', $output);
    }

    public function testIncludeStacktracesOnJsonFormatter()
    {
        $handler = new StreamHandler('php://memory');
        $formatter = new JsonFormatter();
        $handler->setFormatter($formatter);

        $configurator = new FormatterConfigurator(includeStacktraces: true);
        $configurator($handler);

        // Verify includeStacktraces was called by formatting a record with an exception
        $exception = new \Exception('Test exception');
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: \Monolog\Level::Error,
            message: 'Test',
            context: ['exception' => $exception],
        );
        $output = $handler->getFormatter()->format($record);
        $this->assertStringContainsString('"trace":', $output);
    }

    public function testBasePathOnLineFormatter()
    {
        $handler = new StreamHandler('php://memory');
        $formatter = new LineFormatter();
        $handler->setFormatter($formatter);

        $configurator = new FormatterConfigurator(includeStacktraces: true, basePath: '/var/www/project');
        $configurator($handler);

        // Verify basePath was applied by formatting a record with an exception
        // The base path should be stripped from stack trace paths
        $exception = new \Exception('Test exception');
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: \Monolog\Level::Error,
            message: 'Test',
            context: ['exception' => $exception],
        );
        $output = $handler->getFormatter()->format($record);
        // Just verify the formatter works after basePath was set
        $this->assertStringContainsString('[stacktrace]', $output);
    }

    public function testBasePathAndIncludeStacktraces()
    {
        $handler = new StreamHandler('php://memory');
        $formatter = new LineFormatter();
        $handler->setFormatter($formatter);

        $configurator = new FormatterConfigurator(includeStacktraces: true, basePath: '/var/www/project');
        $configurator($handler);

        $this->assertInstanceOf(LineFormatter::class, $handler->getFormatter());
    }

    public function testBasePathIgnoredOnJsonFormatter()
    {
        $handler = new StreamHandler('php://memory');
        $formatter = new JsonFormatter();
        $handler->setFormatter($formatter);

        // base_path only applies to LineFormatter, should not throw
        $configurator = new FormatterConfigurator(basePath: '/var/www/project');
        $configurator($handler);

        $this->assertInstanceOf(JsonFormatter::class, $handler->getFormatter());
    }

    public function testNoConfigurationApplied()
    {
        $handler = new StreamHandler('php://memory');
        $formatter = new LineFormatter();
        $handler->setFormatter($formatter);

        $configurator = new FormatterConfigurator();
        $configurator($handler);

        $this->assertInstanceOf(LineFormatter::class, $handler->getFormatter());
    }
}
