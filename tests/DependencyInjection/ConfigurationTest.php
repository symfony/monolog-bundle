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

use Composer\InstalledVersions;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigurationTest extends TestCase
{
    /**
     * Some basic tests to make sure the configuration is correctly processed in
     * the standard case.
     */
    public function testProcessSimpleCase()
    {
        $configs = [
            [
                'handlers' => ['foobar' => ['type' => 'stream', 'path' => '/foo/bar']],
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
        $this->assertArrayHasKey('foobar', $config['handlers']);
        $this->assertEquals('stream', $config['handlers']['foobar']['type']);
        $this->assertEquals('/foo/bar', $config['handlers']['foobar']['path']);
        $this->assertFalse($config['handlers']['foobar']['nested']);
    }

    public static function provideProcessStringChannels(): array
    {
        return [
            ['foo', 'foo', true],
            ['!foo', 'foo', false],
        ];
    }

    #[DataProvider('provideProcessStringChannels')]
    public function testProcessStringChannels(string $string, string $expectedString, bool $isInclusive)
    {
        $configs = [
            [
                'handlers' => [
                    'foobar' => [
                        'type' => 'stream',
                        'path' => '/foo/bar',
                        'channels' => $string,
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertEquals($isInclusive ? 'inclusive' : 'exclusive', $config['handlers']['foobar']['channels']['type']);
        $this->assertCount(1, $config['handlers']['foobar']['channels']['elements']);
        $this->assertEquals($expectedString, $config['handlers']['foobar']['channels']['elements'][0]);
    }

    public function testUnsetExcludedHttpCodes()
    {
        $configs = [
            [
                'handlers' => [
                    'main' => [
                        'type' => 'fingers_crossed',
                        'action_level' => 'error',
                        'handler' => 'nested',
                        'excluded_http_codes' => [404, 405],
                        'channels' => ['!event'],
                    ],
                    'nested' => [
                        'type' => 'stream',
                        'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                        'level' => 'debug',
                    ],
                ],
            ],
            [
                'handlers' => [
                    'main' => [
                        'type' => 'stream',
                        'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                        'level' => 'debug',
                        'excluded_http_codes' => false,
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayNotHasKey('excluded_http_codes', $config['handlers']['main']);
        $this->assertIsArray($config['handlers']['main']['channels']);
    }

    public static function provideGelfPublisher(): array
    {
        return [
            [
                'gelf.publisher',
            ],
            [
                [
                    'id' => 'gelf.publisher',
                ],
            ],
        ];
    }

    #[DataProvider('provideGelfPublisher')]
    public function testGelfPublisherService(mixed $publisher)
    {
        $configs = [
            [
                'handlers' => [
                    'gelf' => [
                        'type' => 'gelf',
                        'publisher' => $publisher,
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('id', $config['handlers']['gelf']['publisher']);
        $this->assertArrayNotHasKey('hostname', $config['handlers']['gelf']['publisher']);
        $this->assertEquals('gelf.publisher', $config['handlers']['gelf']['publisher']['id']);
    }

    public function testGelfPublisherWithEncoder()
    {
        $configs = [
            [
                'handlers' => [
                    'gelf' => [
                        'type' => 'gelf',
                        'publisher' => [
                            'hostname' => 'localhost',
                            'encoder' => 'compressed_json',
                        ],
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertEquals('localhost', $config['handlers']['gelf']['publisher']['hostname']);
        $this->assertEquals('compressed_json', $config['handlers']['gelf']['publisher']['encoder']);
    }

    public function testArrays()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => ['A', 'B'],
                    ],
                    'bar' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => ['!C', '!D'],
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        // Check foo
        $this->assertCount(2, $config['handlers']['foo']['channels']['elements']);
        $this->assertEquals('inclusive', $config['handlers']['foo']['channels']['type']);
        $this->assertEquals('A', $config['handlers']['foo']['channels']['elements'][0]);
        $this->assertEquals('B', $config['handlers']['foo']['channels']['elements'][1]);

        // Check bar
        $this->assertCount(2, $config['handlers']['bar']['channels']['elements']);
        $this->assertEquals('exclusive', $config['handlers']['bar']['channels']['type']);
        $this->assertEquals('C', $config['handlers']['bar']['channels']['elements'][0]);
        $this->assertEquals('D', $config['handlers']['bar']['channels']['elements'][1]);
    }

    public function testInvalidArrays()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => ['A', '!B'],
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);

        $this->process($configs);
    }

    public function testNativeMailerRejectsNewlineInParameters()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'native_mailer',
                        'from_email' => 'from@example.com',
                        'to_email' => 'to@example.com',
                        'subject' => 'a subject',
                        'parameters' => ["-ftest@example.com\nX-Injected: header"],
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);

        $this->process($configs);
    }

    public function testMergingInvalidChannels()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => 'A',
                    ],
                ],
            ],
            [
                'handlers' => [
                    'foo' => [
                        'channels' => '!B',
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);

        $this->process($configs);
    }

    public function testWithElasticsearchHandler()
    {
        $configs = [
            [
                'handlers' => [
                    'elasticsearch' => [
                        'type' => 'elastic_search',
                        'elasticsearch' => [
                            'id' => 'elastica.client',
                        ],
                        'index' => 'my-index',
                        'document_type' => 'my-record',
                        'ignore_error' => true,
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertTrue($config['handlers']['elasticsearch']['ignore_error']);
        $this->assertEquals('my-record', $config['handlers']['elasticsearch']['document_type']);
        $this->assertEquals('my-index', $config['handlers']['elasticsearch']['index']);
    }

    public function testWithTelegramBotHandler()
    {
        $configs = [
            [
                'handlers' => [
                    'telegram' => [
                        'type' => 'telegram',
                        'token' => 'bot-token',
                        'channel' => '-100',
                        'topic' => 1234,
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertEquals('bot-token', $config['handlers']['telegram']['token']);
        $this->assertEquals('-100', $config['handlers']['telegram']['channel']);
        $this->assertEquals(1234, $config['handlers']['telegram']['topic']);
    }

    #[DataProvider('provideConsoleHandlerCases')]
    public function testWithConsoleHandler(array $verbosityLevels, array $expectedVerbosityMap)
    {
        $configs = [
            [
                'handlers' => [
                    'console' => [
                        'type' => 'console',
                        'verbosity_levels' => $verbosityLevels,
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertSame('console', $config['handlers']['console']['type']);
        $this->assertSame($expectedVerbosityMap, $config['handlers']['console']['verbosity_levels']);
    }

    public static function provideConsoleHandlerCases(): iterable
    {
        yield 'with strings only' => [
            [
                'VERBOSITY_NORMAL' => 'NOTICE',
                'verbosity_verbose' => 'info',
                'VERBOSITY_very_VERBOSE' => '200',
            ],
            [
                OutputInterface::VERBOSITY_NORMAL => Level::Notice->value,
                OutputInterface::VERBOSITY_VERBOSE => Level::Info->value,
                OutputInterface::VERBOSITY_VERY_VERBOSE => 200,
                OutputInterface::VERBOSITY_QUIET => Level::Error->value,
                OutputInterface::VERBOSITY_DEBUG => Level::Debug->value,
            ],
        ];

        yield 'with numeric index' => [
            [
                Level::Alert->value,
                Level::Error->value,
                Level::Warning->value,
                Level::Notice->value,
                Level::Info->value,
            ],
            [
                OutputInterface::VERBOSITY_QUIET => Level::Alert->value,
                OutputInterface::VERBOSITY_NORMAL => Level::Error->value,
                OutputInterface::VERBOSITY_VERBOSE => Level::Warning->value,
                OutputInterface::VERBOSITY_VERY_VERBOSE => Level::Notice->value,
                OutputInterface::VERBOSITY_DEBUG => Level::Info->value,
            ],
        ];

        yield 'with constants' => [
            [
                OutputInterface::VERBOSITY_NORMAL => Level::Notice->value,
                'verbosity_verbose' => 'info',
                OutputInterface::VERBOSITY_VERY_VERBOSE => Level::Info->value,
            ],
            [
                OutputInterface::VERBOSITY_NORMAL => Level::Notice->value,
                OutputInterface::VERBOSITY_VERBOSE => Level::Info->value,
                OutputInterface::VERBOSITY_VERY_VERBOSE => Level::Info->value,
                OutputInterface::VERBOSITY_QUIET => Level::Error->value,
                OutputInterface::VERBOSITY_DEBUG => Level::Debug->value,
            ],
        ];
    }

    public function testWithType()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => [
                            'type' => 'inclusive',
                            'elements' => ['A', 'B'],
                        ],
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        // Check foo
        $this->assertCount(2, $config['handlers']['foo']['channels']['elements']);
        $this->assertEquals('inclusive', $config['handlers']['foo']['channels']['type']);
        $this->assertEquals('A', $config['handlers']['foo']['channels']['elements'][0]);
        $this->assertEquals('B', $config['handlers']['foo']['channels']['elements'][1]);
    }

    public function testWithFilePermission()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'file_permission' => '0666',
                    ],
                    'bar' => [
                        'type' => 'stream',
                        'path' => '/bar',
                        'file_permission' => 0777,
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertSame(0666, $config['handlers']['foo']['file_permission']);
        $this->assertSame(0777, $config['handlers']['bar']['file_permission']);
    }

    public function testWithUseLocking()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'use_locking' => false,
                    ],
                    'bar' => [
                        'type' => 'rotating_file',
                        'path' => '/bar',
                        'use_locking' => true,
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertFalse($config['handlers']['foo']['use_locking']);
        $this->assertTrue($config['handlers']['bar']['use_locking']);
    }

    public function testWithNestedHandler()
    {
        $configs = [
            [
                'handlers' => ['foobar' => ['type' => 'stream', 'path' => '/foo/bar', 'nested' => true]],
            ],
        ];

        $config = $this->process($configs);

        $this->assertTrue($config['handlers']['foobar']['nested']);
    }

    public function testWithRedisHandler()
    {
        $configs = [
            [
                'handlers' => [
                    'redis' => [
                        'type' => 'redis',
                        'redis' => [
                            'host' => '127.0.1.1',
                            'password' => 'pa$$w0rd',
                            'port' => 1234,
                            'database' => 1,
                            'key_name' => 'monolog_redis_test',
                        ],
                    ],
                ],
            ],
        ];
        $config = $this->process($configs);

        $this->assertEquals('127.0.1.1', $config['handlers']['redis']['redis']['host']);
        $this->assertEquals('pa$$w0rd', $config['handlers']['redis']['redis']['password']);
        $this->assertEquals(1234, $config['handlers']['redis']['redis']['port']);
        $this->assertEquals(1, $config['handlers']['redis']['redis']['database']);
        $this->assertEquals('monolog_redis_test', $config['handlers']['redis']['redis']['key_name']);

        $configs = [
            [
                'handlers' => [
                    'redis' => [
                        'type' => 'predis',
                        'redis' => [
                            'host' => '127.0.1.1',
                            'key_name' => 'monolog_redis_test',
                        ],
                    ],
                ],
            ],
        ];
        $config = $this->process($configs);

        $this->assertEquals('127.0.1.1', $config['handlers']['redis']['redis']['host']);
        $this->assertEquals('monolog_redis_test', $config['handlers']['redis']['redis']['key_name']);
    }

    public function testWithSyslogUdpHandler()
    {
        $configs = [
            [
                'handlers' => [
                    'syslogudp' => [
                        'type' => 'syslogudp',
                        'host' => '127.0.0.1',
                        'port' => 514,
                        'facility' => 'USER',
                        'level' => 'ERROR',
                        'rfc' => SyslogUdpHandler::RFC3164,
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertEquals('syslogudp', $config['handlers']['syslogudp']['type']);
        $this->assertEquals('127.0.0.1', $config['handlers']['syslogudp']['host']);
        $this->assertEquals(514, $config['handlers']['syslogudp']['port']);
        $this->assertEquals('php', $config['handlers']['syslogudp']['ident']);
        $this->assertEquals(SyslogUdpHandler::RFC3164, $config['handlers']['syslogudp']['rfc']);

        $configs = [
            [
                'handlers' => [
                    'syslogudp' => [
                        'type' => 'syslogudp',
                        'host' => '127.0.0.1',
                        'port' => 514,
                        'facility' => 'USER',
                        'level' => 'ERROR',
                    ],
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertEquals('syslogudp', $config['handlers']['syslogudp']['type']);
        $this->assertEquals('127.0.0.1', $config['handlers']['syslogudp']['host']);
        $this->assertEquals(514, $config['handlers']['syslogudp']['port']);
        $this->assertEquals('php', $config['handlers']['syslogudp']['ident']);
        $this->assertEquals(SyslogUdpHandler::RFC5424, $config['handlers']['syslogudp']['rfc']);
    }

    #[DataProvider('processPsr3MessagesProvider')]
    public function testWithProcessPsr3Messages(array $configuration, array $processedConfiguration)
    {
        $configs = [
            [
                'handlers' => [
                    'main' => ['type' => 'stream'] + $configuration,
                ],
            ],
        ];

        $config = $this->process($configs);

        $this->assertEquals($processedConfiguration, $config['handlers']['main']['process_psr_3_messages']);
    }

    public static function processPsr3MessagesProvider(): iterable
    {
        yield 'Not specified' => [[], ['enabled' => null]];

        if (version_compare(InstalledVersions::getVersion('symfony/config'), '7.2', '>=')) {
            yield 'Null' => [['process_psr_3_messages' => null], ['enabled' => null]];
        } else {
            yield 'Null' => [['process_psr_3_messages' => null], ['enabled' => true]];
        }

        yield 'True' => [['process_psr_3_messages' => true], ['enabled' => true]];
        yield 'False' => [['process_psr_3_messages' => false], ['enabled' => false]];

        yield 'Date format' => [
            ['process_psr_3_messages' => ['date_format' => 'Y']],
            ['date_format' => 'Y', 'enabled' => null],
        ];
        yield 'Enabled false & remove used' => [
            ['process_psr_3_messages' => ['enabled' => false, 'remove_used_context_fields' => true]],
            ['enabled' => false, 'remove_used_context_fields' => true],
        ];
    }

    /**
     * Processes an array of configurations and returns a compiled version.
     *
     * @param array $configs An array of raw configurations
     *
     * @return array A normalized array
     */
    private function process(array $configs): array
    {
        $processor = new Processor();

        return $processor->processConfiguration(new Configuration(), $configs);
    }
}
