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

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\DependencyInjection\Configuration;
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
                'handlers' => ['foobar' => ['type' => 'stream', 'path' => '/foo/bar']]
            ]
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
        $this->assertArrayHasKey('foobar', $config['handlers']);
        $this->assertEquals('stream', $config['handlers']['foobar']['type']);
        $this->assertEquals('/foo/bar', $config['handlers']['foobar']['path']);
        $this->assertFalse($config['handlers']['foobar']['nested']);
    }

    public function provideProcessStringChannels()
    {
        return [
            ['foo', 'foo', true],
            ['!foo', 'foo', false]
        ];
    }

    /**
     * @dataProvider provideProcessStringChannels
     */
    public function testProcessStringChannels($string, $expectedString, $isInclusive)
    {
        $configs = [
            [
                'handlers' => [
                    'foobar' => [
                        'type' => 'stream',
                        'path' => '/foo/bar',
                        'channels' => $string
                    ]
                ]
            ]
        ];

        $config = $this->process($configs);

        $this->assertEquals($isInclusive ? 'inclusive' : 'exclusive', $config['handlers']['foobar']['channels']['type']);
        $this->assertCount(1, $config['handlers']['foobar']['channels']['elements']);
        $this->assertEquals($expectedString, $config['handlers']['foobar']['channels']['elements'][0]);
    }

    public function provideGelfPublisher()
    {
        return [
            [
                'gelf.publisher'
            ],
            [
                [
                    'id' => 'gelf.publisher'
                ]
            ]
        ];
    }

    /**
     * @dataProvider provideGelfPublisher
     */
    public function testGelfPublisherService($publisher)
    {
        $configs = [
            [
                'handlers' => [
                    'gelf' => [
                        'type' => 'gelf',
                        'publisher' => $publisher,
                    ],
                ]
            ]
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('id', $config['handlers']['gelf']['publisher']);
        $this->assertArrayNotHasKey('hostname', $config['handlers']['gelf']['publisher']);
        $this->assertEquals('gelf.publisher', $config['handlers']['gelf']['publisher']['id']);
    }

    public function testArrays()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => ['A', 'B']
                    ],
                    'bar' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => ['!C', '!D']
                    ],
                ]
            ]
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

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInvalidArrays()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => ['A', '!B']
                    ]
                ]
            ]
        ];

        $config = $this->process($configs);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testMergingInvalidChannels()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => [
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => 'A',
                    ]
                ]
            ],
            [
                'handlers' => [
                    'foo' => [
                        'channels' => '!B',
                    ]
                ]
            ]
        ];

        $config = $this->process($configs);
    }

    public function testWithSwiftMailerHandler()
    {
        $configs = [
            [
                'handlers' => [
                    'swift' => [
                        'type' => 'swift_mailer',
                        'from_email' => 'foo@bar.com',
                        'to_email' => 'foo@bar.com',
                        'subject' => 'Subject',
                        'mailer'  => 'mailer',
                        'email_prototype' => [
                            'id' => 'monolog.prototype',
                            'method' => 'getPrototype'
                        ]
                    ]
                ]
            ]
        ];

        $config = $this->process($configs);

        // Check email_prototype
        $this->assertCount(2, $config['handlers']['swift']['email_prototype']);
        $this->assertEquals('monolog.prototype', $config['handlers']['swift']['email_prototype']['id']);
        $this->assertEquals('getPrototype', $config['handlers']['swift']['email_prototype']['method']);
        $this->assertEquals('mailer', $config['handlers']['swift']['mailer']);
    }

    public function testWithElasticsearchHandler()
    {
        $configs = [
            [
                'handlers' => [
                    'elasticsearch' => [
                        'type' => 'elasticsearch',
                        'elasticsearch' => [
                            'id' => 'elastica.client'
                        ],
                        'index' => 'my-index',
                        'document_type' => 'my-record',
                        'ignore_error' => true
                    ]
                ]
            ]
        ];

        $config = $this->process($configs);

        $this->assertEquals(true, $config['handlers']['elasticsearch']['ignore_error']);
        $this->assertEquals('my-record', $config['handlers']['elasticsearch']['document_type']);
        $this->assertEquals('my-index', $config['handlers']['elasticsearch']['index']);
    }

    public function testWithConsoleHandler()
    {
        $configs = [
            [
                'handlers' => [
                    'console' => [
                        'type' => 'console',
                        'verbosity_levels' => [
                            'VERBOSITY_NORMAL' => 'NOTICE',
                            'verbosity_verbose' => 'info',
                            'VERBOSITY_very_VERBOSE' => 150
                        ]
                    ]
                ]
            ]
        ];

        $config = $this->process($configs);

        $this->assertSame('console', $config['handlers']['console']['type']);
        $this->assertSame([
            OutputInterface::VERBOSITY_NORMAL => Logger::NOTICE,
            OutputInterface::VERBOSITY_VERBOSE => Logger::INFO,
            OutputInterface::VERBOSITY_VERY_VERBOSE => 150,
            OutputInterface::VERBOSITY_QUIET => Logger::ERROR,
            OutputInterface::VERBOSITY_DEBUG => Logger::DEBUG
            ], $config['handlers']['console']['verbosity_levels']);
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
                            'elements' => ['A', 'B']
                        ]
                    ]
                ]
            ]
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
                        'file_permission' => 0777
                    ]
                ]
            ]
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
                        'type' => 'stream',
                        'path' => '/bar',
                        'use_locking' => true,
                    ]
                ]
            ]
        ];

        $config = $this->process($configs);

        $this->assertFalse($config['handlers']['foo']['use_locking']);
        $this->assertTrue($config['handlers']['bar']['use_locking']);
    }

    public function testWithNestedHandler()
    {
        $configs = [
            [
                'handlers' => ['foobar' => ['type' => 'stream', 'path' => '/foo/bar', 'nested' => true]]
            ]
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
                            'key_name' => 'monolog_redis_test'
                        ]
                    ]
                ]
            ]
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
                            'key_name' => 'monolog_redis_test'
                        ]
                    ]
                ]
            ]
        ];
        $config = $this->process($configs);

        $this->assertEquals('127.0.1.1', $config['handlers']['redis']['redis']['host']);
        $this->assertEquals('monolog_redis_test', $config['handlers']['redis']['redis']['key_name']);
    }

    /**
     * Processes an array of configurations and returns a compiled version.
     *
     * @param array $configs An array of raw configurations
     *
     * @return array A normalized array
     */
    protected function process($configs)
    {
        $processor = new Processor();

        return $processor->processConfiguration(new Configuration(), $configs);
    }

    /**
     * @dataProvider provideStreamHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testStreamHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'stream'], ['path' => '/a/path', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideStreamHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideStreamHandlerValidConfigurationParams()
        );
    }

    private function provideAllHandlersConfigurationParams()
    {
        return [
            'accepted_levels' => ['accepted_levels', ['DEBUG']],
            'action_level' => ['action_level', 'DEBUG'],
            'activation_strategy' => ['activation_strategy', 'DEBUG'],
            'api_version' => ['api_version', 'v2'],
            'app_name' => ['app_name', 'app_name'],
            'auto_log_stacks' => ['auto_log_stacks', true],
            'bubble' => ['bubble', false],
            'bot_name' => ['bot_name', 'bot_name'],
            'buffer_size' => ['buffer_size', 0],
            'channel' => ['channel', '#channel_name'],
            'client_id' => ['client_id', 'client_id'],
            'config' => ['config', ['config' => 'config']],
            'connection_string' => ['connection_string', 'connection_string'],
            'connection_timeout' => ['connection_timeout', 0.5],
            'console_formater_options' => ['console_formater_options', []],
            'content_type' => ['content_type', 'text/plain'],
            'date_format' => ['date_format', 'Y-m-d'],
            'deduplication_level' => ['deduplication_level', 'DEBUG'],
            'document_type' => ['document_type', 'logs'],
            'dsn' => ['dsn', 'dsn_connection_string'],
            'elasticsearch' => ['elasticsearch', 'id'],
            'email_prototype' => ['email_prototype', 'service_message_id'],
            'environment' => ['environment', 'dev'],
            'exchange' => ['exchange', 'service_id'],
            'exchange_name' => ['exchange_name', 'log'],
            'excluded_404s' => ['excluded_404s', ['^/.*']],
            'excluded_http_codes' => ['excluded_http_codes', [404]],
            'facility' => ['facility', 'user'],
            'filename_format' => ['filename_format', 'filename_format'],
            'file_permission' => ['file_permission', 777],
            'flush_on_overflow' => ['flush_on_overflow', true],
            'from_email' => ['from_email', 'fromemail@test.com'],
            'handler' => ['handler', 'handler_name'],
            'headers' => ['headers', ['Foo: Bar']],
            'host' => ['host', 'hostname'],
            'icon_emoji' => ['icon_emoji', ':icon_emoji:'],
            'id' => ['id', 'id'],
            'ident' => ['ident', 'ident'],
            'include_extra' => ['include_extra', true],
            'index' => ['index', 'monolog'],
            'lazy' => ['lazy', false],
            'level' => ['level', 'DEBUG'],
            'logopts' => ['logopts', 'LOGPID'],
            'mailer' => ['mailer', 'mailer'],
            'max_files' => ['max_files', 0],
            'max_level' => ['max_level', 'DEBUG'],
            'message_format' => ['message_format', 'html'],
            'message_type' => ['message_type', '1'],
            'min_level' => ['min_level', 'DEBUG'],
            'members' => ['members', ['foo']],
            'mongo' => ['mongo', 'id'],
            'nickname' => ['nickname', 'nickname'],
            'notify' => ['notify', true],
            'path' => ['path', '/a/path'],
            'passthru_level' => ['passthru_level', 'DEBUG'],
            'persistent' => ['persistent', true],
            'port' => ['port', 514],
            'publisher' => ['publisher', 'id'],
            'redis' => ['redis', ['id' => 'id']],
            'release' => ['release', '1.0.1'],
            'region' => ['region', 'eu'],
            'room' => ['room', 'room_id'],
            'source' => ['source', 'source_id'],
            'stop_buffering' => ['stop_buffering', false],
            'store' => ['store', '/'],
            'subject' => ['subject', 'subject'],
            'tags' => ['tags', ['a_tag']],
            'team' => ['team', 'team'],
            'time' => ['time', 3600],
            'timeout' => ['timeout', 1.2],
            'title' => ['title', 'title'],
            'token' => ['token', 'api_token'],
            'to_email' => ['to_email', 'toemail@test.com'],
            'url' => ['url', 'http://localhost'],
            'user' => ['user', 'user_id'],
            'use_attachment' => ['use_attachment', false],
            'use_locking' => ['use_locking', true],
            'use_short_attachment' => ['use_short_attachment', true],
            'use_ssl' => ['use_ssl', false],
            'verbosity_levels' => ['verbosity_levels', ['DEBUG']],
            'webhook_url' => ['webhook_url', 'http://localhost'],
        ];
    }

    private function provideStreamHandlerValidConfigurationParams()
    {
        return [
            'path' => 'aPath',
            'level' => 'DEBUG',
            'bubble' => false,
            'file_permission' => 777,
            'use_locking' => true,
        ];
    }

    public function testStreamHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'stream'], $this->provideStreamHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideConsoleHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testConsoleHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'console'], [$param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideConsoleHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideConsoleHandlerValidConfigurationParams()
        );
    }

    private function provideConsoleHandlerValidConfigurationParams()
    {
        return [
            'verbosity_levels' => ['DEBUG'],
            'level' => 'DEBUG',
            'bubble' => false,
            'console_formater_options' => [],
        ];
    }

    public function testConsoleHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'console'], $this->provideConsoleHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideFirePHPHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testFirePHPHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'firephp'], [$param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideFirePHPHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideFirePHPHandlerValidConfigurationParams()
        );
    }

    private function provideFirePHPHandlerValidConfigurationParams()
    {
        return [
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testFirePHPHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'firephp'], $this->provideFirePHPHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideBrowserConsoleHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testBrowserConsoleHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'browser_console'], [$param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideBrowserConsoleHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideBrowserConsoleHandlerValidConfigurationParams()
        );
    }

    private function provideBrowserConsoleHandlerValidConfigurationParams()
    {
        return [
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testBrowserConsoleHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'browser_console'], $this->provideBrowserConsoleHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideGelfHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testGelfHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'gelf'], ['publisher' => 'id', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideGelfHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideGelfHandlerValidConfigurationParams()
        );
    }

    private function provideGelfHandlerValidConfigurationParams()
    {
        return [
            'publisher' => 'id',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testGelfHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'gelf'], $this->provideGelfHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideChromePHPHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testChromePHPHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'chromephp'], [$param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideChromePHPHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideChromePHPHandlerValidConfigurationParams()
        );
    }

    private function provideChromePHPHandlerValidConfigurationParams()
    {
        return [
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testChromePHPHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'chromephp'],
                        $this->provideChromePHPHandlerValidConfigurationParams()
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideRotatingFileHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testRotatingFileHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'rotating_file'], ['path' => '/a/path', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideRotatingFileHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideRotatingFileHandlerValidConfigurationParams()
        );
    }

    private function provideRotatingFileHandlerValidConfigurationParams()
    {
        return [
            'path' => '/a/path',
            'max_files' => 0,
            'level' => 'DEBUG',
            'bubble' => false,
            'file_permission' => 777,
            'filename_format' => 'filename_format',
            'date_format' => 'Y-m-d',
        ];
    }

    public function testRotatingFileHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'rotating_file'],
                        $this->provideRotatingFileHandlerValidConfigurationParams()
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideMongoHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testMongoHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'mongo'], ['mongo' => 'id', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideMongoHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideMongoHandlerValidConfigurationParams()
        );
    }

    private function provideMongoHandlerValidConfigurationParams()
    {
        return [
            'mongo' => 'id',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testMongoHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'mongo'], $this->provideMongoHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideElasticSearchHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testElasticSearchHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'elasticsearch'], ['elasticsearch' => 'id', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideElasticSearchHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideElasticSearchHandlerValidConfigurationParams()
        );
    }

    private function provideElasticSearchHandlerValidConfigurationParams()
    {
        return [
            'elasticsearch' => 'id',
            'index' => 'monolog',
            'document_type' => 'logs',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testElasticSearchHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'elasticsearch'],
                        $this->provideElasticSearchHandlerValidConfigurationParams()
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideRedisHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testRedisHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'redis'], ['redis' => ['id' => 'id'], $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideRedisHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideRedisHandlerValidConfigurationParams()
        );
    }

    private function provideRedisHandlerValidConfigurationParams()
    {
        return [
            'redis' => ['id' => 'id'],
        ];
    }

    public function testRedisHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'redis'], $this->provideRedisHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider providePredisHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testPredisHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'predis'], ['redis' => ['id' => 'id'], $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function providePredisHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->providePredisHandlerValidConfigurationParams()
        );
    }

    private function providePredisHandlerValidConfigurationParams()
    {
        return [
            'redis' => ['id' => 'id'],
        ];
    }

    public function testPredisHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'redis'], $this->providePredisHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideFingersCrossedHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testFingersCrossedHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'fingers_crossed'], ['handler' => 'handler_name', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideFingersCrossedHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideFingersCrossedHandlerValidConfigurationParams()
        );
    }

    private function provideFingersCrossedHandlerValidConfigurationParams()
    {
        return [
            'handler' => 'handler_name',
            'action_level' => 'DEBUG',
            'activation_strategy' => 'DEBUG',
            'excluded_404s' => ['^/.*'],
            'excluded_http_codes' => [404],
            'buffer_size' => 0,
            'stop_buffering' => false,
            'passthru_level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testFingersCrossedHandlerWithActionLevelExcluded404sConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'fingers_crossed'],
                        [
                            'handler' => 'handler_name',
                            'action_level' => 'DEBUG',
                            'excluded_http_codes' => [404],
                            'buffer_size' => 0,
                            'stop_buffering' => false,
                            'passthru_level' => 'DEBUG',
                            'bubble' => false,
                        ]
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    public function testFingersCrossedHandlerWithActionLevelExcludedHttpCodesConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'fingers_crossed'],
                        [
                            'handler' => 'handler_name',
                            'action_level' => 'DEBUG',
                            'excluded_404s' => ['^/.*'],
                            'buffer_size' => 0,
                            'stop_buffering' => false,
                            'passthru_level' => 'DEBUG',
                            'bubble' => false,
                        ]
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    public function testFingersCrossedHandlerWithActivationStrategyConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'fingers_crossed'],
                        [
                            'handler' => 'handler_name',
                            'activation_strategy' => 'DEBUG',
                            'buffer_size' => 0,
                            'stop_buffering' => false,
                            'passthru_level' => 'DEBUG',
                            'bubble' => false,
                        ]
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideFilterHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testFilterHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'filter'], ['handler' => 'handler_name', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideFilterHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideFilterHandlerValidConfigurationParams()
        );
    }

    private function provideFilterHandlerValidConfigurationParams()
    {
        return [
            'handler' => 'handler_name',
            'accepted_levels' => ['DEBUG'],
            'min_level' => 'DEBUG',
            'max_level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testFilterHandlerWithAcceptedLevelsValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'filter'], [
                        'handler' => 'handler_name',
                        'accepted_levels' => ['DEBUG'],
                        'bubble' => false,
                    ]),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    public function testFilterHandlerWithMinMaxLevelsValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'filter'], [
                        'handler' => 'handler_name',
                        'min_level' => 'DEBUG',
                        'max_level' => 'DEBUG',
                        'bubble' => false,
                    ]),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideBufferHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testBufferHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'buffer'], ['handler' => 'handler_name', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideBufferHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideBufferHandlerValidConfigurationParams()
        );
    }

    private function provideBufferHandlerValidConfigurationParams()
    {
        return [
            'handler' => 'handler_name',
            'buffer_size' => 0,
            'level' => 'DEBUG',
            'bubble' => false,
            'flush_on_overflow' => true,
        ];
    }

    public function testBufferHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'buffer'], $this->provideBufferHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideDeduplicationHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testDeduplicationHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'deduplication'], ['handler' => 'handler_name', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideDeduplicationHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideDeduplicationHandlerValidConfigurationParams()
        );
    }

    private function provideDeduplicationHandlerValidConfigurationParams()
    {
        return [
            'handler' => 'handler_name',
            'store' => '/',
            'deduplication_level' => 'DEBUG',
            'time' => 3600,
            'bubble' => false,
        ];
    }

    public function testDeduplicationHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'deduplication'], $this->provideDeduplicationHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideGroupHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testGroupHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'group'], ['members' => ['foo'], $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideGroupHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideGroupHandlerValidConfigurationParams()
        );
    }

    private function provideGroupHandlerValidConfigurationParams()
    {
        return [
            'members' => ['foo'],
            'bubble' => false,
        ];
    }

    public function testGroupHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'group'], $this->provideGroupHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideWhatFailureGroupHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testWhatFailureGroupHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'whatfailuregroup'], ['members' => ['foo'], $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideWhatFailureGroupHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideWhatFailureGroupHandlerValidConfigurationParams()
        );
    }

    private function provideWhatFailureGroupHandlerValidConfigurationParams()
    {
        return [
            'members' => ['foo'],
            'bubble' => false,
        ];
    }

    public function testWhatFailureGroupHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'group'],
                        $this->provideWhatFailureGroupHandlerValidConfigurationParams()
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideSyslogHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testSyslogHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'syslog'], ['ident' => 'ident', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideSyslogHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideSyslogHandlerValidConfigurationParams()
        );
    }

    private function provideSyslogHandlerValidConfigurationParams()
    {
        return [
            'ident' => 'ident',
            'facility' => 'user',
            'logopts' => 'LOGPID',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testSyslogHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'syslog'], $this->provideSyslogHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideSyslogUDPHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testSyslogUDPHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'syslogudp'], ['host' => 'hostname', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideSyslogUDPHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideSyslogUDPHandlerValidConfigurationParams()
        );
    }

    private function provideSyslogUDPHandlerValidConfigurationParams()
    {
        return [
            'host' => 'hostname',
            'port' => 541,
            'facility' => 'user',
            'logopts' => 'LOGPID',
            'level' => 'DEBUG',
            'bubble' => false,
            'ident' => 'ident'
        ];
    }

    public function testSyslogUDPHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'syslogudp'], $this->provideSyslogUDPHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideSwiftMailerHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testSwitftMailerHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'swift_mailer'],
                        [
                            'from_email' => 'fromemail@test.com',
                            'to_email' => 'toemail@test.com',
                            'subject' => 'subject',
                            $param => $value
                        ]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideSwiftMailerHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideSwiftMailerHandlerValidConfigurationParams()
        );
    }

    private function provideSwiftMailerHandlerValidConfigurationParams()
    {
        return [
            'from_email' => 'fromemail@test.com',
            'to_email' => 'toemail@test.com',
            'subject' => 'subject',
            'email_prototype' => 'service_message_id',
            'content_type' => 'text/plain',
            'mailer' => 'mailer',
            'level' => 'DEBUG',
            'bubble' => false,
            'lazy' => false,
        ];
    }

    public function testSwiftMailerHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'swift_mailer'],
                        $this->provideSwiftMailerHandlerValidConfigurationParams()
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideNativeMailerHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testNativeMailerHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'native_mailer'],
                        [
                            'from_email' => 'fromemail@test.com',
                            'to_email' => 'toemail@test.com',
                            'subject' => 'subject',
                            $param => $value
                        ]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideNativeMailerHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideNativeMailerHandlerValidConfigurationParams()
        );
    }

    private function provideNativeMailerHandlerValidConfigurationParams()
    {
        return [
            'from_email' => 'fromemail@test.com',
            'to_email' => 'toemail@test.com',
            'subject' => 'subject',
            'level' => 'DEBUG',
            'bubble' => false,
            'headers' => ['Foo: Bar'],
        ];
    }

    public function testNativeMailerHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'native_mailer'],
                        $this->provideNativeMailerHandlerValidConfigurationParams()
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideSocketHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testSocketHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'socket'],
                        ['connection_string' => 'connection_string', $param => $value]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideSocketHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideSocketHandlerValidConfigurationParams()
        );
    }

    private function provideSocketHandlerValidConfigurationParams()
    {
        return [
            'connection_string' => 'connection_string',
            'timeout' => 1.2,
            'connection_timeout' => 0.5,
            'persistent' => true,
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testSocketHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'socket'], $this->provideSocketHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider providePushoverHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testPushoverHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'pushover'],
                        ['token' => 'api_token', 'user' => 'user_id', $param => $value]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function providePushoverHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->providePushoverHandlerValidConfigurationParams()
        );
    }

    private function providePushoverHandlerValidConfigurationParams()
    {
        return [
            'token' => 'api_token',
            'user' => 'user_id',
            'title' => 'title',
            'level' => 'DEBUG',
            'bubble' => false,
            'timeout' => 1.2,
            'connection_timeout' => 0.5,
        ];
    }

    public function testPushoverHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'pushover'], $this->providePushoverHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideRavenSentryHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testRavenHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'raven'],
                        ['dsn' => 'dsn_connection_string', 'client_id' => 'client_id', $param => $value]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideRavenSentryHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideRavenSentryHandlerValidConfigurationParams()
        );
    }

    private function provideRavenSentryHandlerValidConfigurationParams()
    {
        return [
            'dsn' => 'dsn_connection_string',
            'client_id' => 'client_id',
            'release' => '1.0.1',
            'level' => 'DEBUG',
            'bubble' => false,
            'auto_log_stacks' => true,
            'environment' => 'dev',
        ];
    }

    public function testRavenHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'raven'], $this->provideRavenSentryHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideRavenSentryHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testSentryHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'sentry'],
                        ['dsn' => 'dsn_connection_string', 'client_id' => 'client_id', $param => $value]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function testSentryHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'sentry'], $this->provideRavenSentryHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideNewrelicHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testNewrelicHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'newrelic'], [$param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideNewrelicHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideNewrelicHandlerValidConfigurationParams()
        );
    }

    private function provideNewrelicHandlerValidConfigurationParams()
    {
        return [
            'level' => 'DEBUG',
            'bubble' => false,
            'app_name' => 'app_name'
        ];
    }

    public function testNewrelicHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'newrelic'], $this->provideNewrelicHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideHipchatHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testHipchatHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'hipchat'],
                        ['token' => 'api_token', 'room' => 'room_id', $param => $value]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideHipchatHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideHipchatHandlerValidConfigurationParams()
        );
    }

    private function provideHipchatHandlerValidConfigurationParams()
    {
        return [
            'token' => 'api_token',
            'room' => 'room_id',
            'notify' => true,
            'nickname' => 'nickname',
            'level' => 'DEBUG',
            'bubble' => false,
            'use_ssl' => false,
            'message_format' => 'html',
            'host' => 'hostname',
            'api_version' => 'v2',
            'timeout' => 1.2,
            'connection_timeout' => 0.5,
        ];
    }

    public function testHipchatHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'hipchat'], $this->provideHipchatHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideSlackHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testSlackHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'slack'],
                        ['token' => 'api_token', 'channel' => '#channel_name', $param => $value]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideSlackHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideSlackHandlerValidConfigurationParams()
        );
    }

    private function provideSlackHandlerValidConfigurationParams()
    {
        return [
            'token' => 'api_token',
            'channel' => '#channel_name',
            'bot_name' => 'bot_name',
            'icon_emoji' => ':icon_emoji:',
            'use_attachment' => false,
            'use_short_attachment' => true,
            'include_extra' => true,
            'level' => 'DEBUG',
            'bubble' => false,
            'timeout' => 1.2,
            'connection_timeout' => 0.5,
        ];
    }

    public function testSlackHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'slack'], $this->provideSlackHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideSlackWebHookHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testSlackWebHookHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'slackwebhook'],
                        ['webhook_url' => 'http://localhost', 'channel' => '#channel_name', $param => $value]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideSlackWebHookHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideSlackWebHookHandlerValidConfigurationParams()
        );
    }

    private function provideSlackWebHookHandlerValidConfigurationParams()
    {
        return [
            'webhook_url' => 'http://localhost',
            'channel' => '#channel_name',
            'bot_name' => 'bot_name',
            'icon_emoji' => ':icon_emoji:',
            'use_attachment' => false,
            'use_short_attachment' => true,
            'include_extra' => true,
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testSlackWebHookHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'slackwebhook'],
                        $this->provideSlackWebHookHandlerValidConfigurationParams()
                    ),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideSlackBotHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testSlackBotHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'slackbot'],
                        ['team' => 'team', 'token' => 'api_token', 'channel' => '#channel_name', $param => $value]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideSlackBotHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideSlackBotHandlerValidConfigurationParams()
        );
    }

    private function provideSlackBotHandlerValidConfigurationParams()
    {
        return [
            'team' => 'team',
            'token' => 'api_token',
            'channel' => '#channel_name',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testSlackBotHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'slackbot'], $this->provideSlackBotHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideCubeHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testCubeHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'cube'], ['url' => 'http://localhost', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideCubeHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideCubeHandlerValidConfigurationParams()
        );
    }

    private function provideCubeHandlerValidConfigurationParams()
    {
        return [
            'url' => 'http://localhost',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testCubeHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'cube'], $this->provideCubeHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideAmqpHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testAmqpHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'amqp'], ['exchange' => 'service_id', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideAmqpHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideAmqpHandlerValidConfigurationParams()
        );
    }

    private function provideAmqpHandlerValidConfigurationParams()
    {
        return [
            'exchange' => 'service_id',
            'exchange_name' => 'log',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testAmqpHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'amqp'], $this->provideAmqpHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideErrorLogHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testErrorLogHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'error_log'], [$param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideErrorLogHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideErrorLogHandlerValidConfigurationParams()
        );
    }

    private function provideErrorLogHandlerValidConfigurationParams()
    {
        return [
            'message_type' => '1',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testErrorLogHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'error_log'], $this->provideErrorLogHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideNullHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testNullHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'null'], [$param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideNullHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideNullHandlerValidConfigurationParams()
        );
    }

    private function provideNullHandlerValidConfigurationParams()
    {
        return [
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testNullHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'null'], $this->provideNullHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideTestHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testTestHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'test'], [$param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideTestHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideTestHandlerValidConfigurationParams()
        );
    }

    private function provideTestHandlerValidConfigurationParams()
    {
        return [
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testTestHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'test'], $this->provideTestHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideDebugHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testDebugHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'debug'], [$param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideDebugHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideDebugHandlerValidConfigurationParams()
        );
    }

    private function provideDebugHandlerValidConfigurationParams()
    {
        return [
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testDebugHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'debug'], $this->provideDebugHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideLogglyHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testLogglyHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'loggly'], ['token' => 'api_token', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideLogglyHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideLogglyHandlerValidConfigurationParams()
        );
    }

    private function provideLogglyHandlerValidConfigurationParams()
    {
        return [
            'token' => 'api_token',
            'level' => 'DEBUG',
            'bubble' => false,
            'tags' => ['a_tag'],
        ];
    }

    public function testLogglyHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'loggly'], $this->provideLogglyHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideLogEntriesHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testLogEntriesHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'logentries'], ['token' => 'api_token', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideLogEntriesHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideLogEntriesHandlerValidConfigurationParams()
        );
    }

    private function provideLogEntriesHandlerValidConfigurationParams()
    {
        return [
            'token' => 'api_token',
            'use_ssl' => false,
            'level' => 'DEBUG',
            'bubble' => false,
            'timeout' => 1.2,
            'connection_timeout' => 0.5,
        ];
    }

    public function testLogEntriesHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'logentries'], $this->provideLogEntriesHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideInsightopsHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInsightopsHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'insightops'],
                        ['token' => 'api_token', 'region' => 'eu', $param => $value]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideInsightopsHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideInsightopsHandlerValidConfigurationParams()
        );
    }

    private function provideInsightopsHandlerValidConfigurationParams()
    {
        return [
            'token' => 'api_token',
            'region' => 'eu',
            'use_ssl' => false,
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testInsightopsHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'insightops'], $this->provideInsightopsHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideFlowdockHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testFlowdockHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'flowdock'],
                        [
                            'token' => 'api_token',
                            'source' => 'source_id',
                            'from_email' => 'fromemail@test.com',
                            $param => $value
                        ]
                    ),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideFlowdockHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideFlowdockHandlerValidConfigurationParams()
        );
    }

    private function provideFlowdockHandlerValidConfigurationParams()
    {
        return [
            'token' => 'api_token',
            'source' => 'source_id',
            'from_email' => 'fromemail@test.com',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testFlowdockHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'flowdock'], $this->provideFlowdockHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideRollbarHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testRollbarHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'rollbar'], ['id' => 'id', 'token' => 'api_token', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideRollbarHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideRollbarHandlerValidConfigurationParams()
        );
    }

    private function provideRollbarHandlerValidConfigurationParams()
    {
        return [
            'id' => 'id',
            'token' => 'api_token',
            'config' => ['config' => 'config'],
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testRollbarHandlerValidIdConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'rollbar'],
                        [
                            'id' => 'id',
                            'config' => ['config' => 'config'],
                            'level' => 'DEBUG',
                            'bubble' => false,
                        ]
                    )
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    public function testRollbarHandlerValidTokenConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(
                        ['type' => 'rollbar'],
                        [
                            'token' => 'api_token',
                            'config' => ['config' => 'config'],
                            'level' => 'DEBUG',
                            'bubble' => false,
                        ]
                    )
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }

    /**
     * @dataProvider provideServerLogHandlerConfigurationInvalidParams
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testServerLogHandlerInvalidConfiguration($param, $value)
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'server_log'], ['host' => 'hostname', $param => $value]),
                ]
            ],
        ];

        $this->process($configs);
    }

    public function provideServerLogHandlerConfigurationInvalidParams()
    {
        return array_diff_key(
            $this->provideAllHandlersConfigurationParams(),
            $this->provideServerLogHandlerValidConfigurationParams()
        );
    }

    private function provideServerLogHandlerValidConfigurationParams()
    {
        return [
            'host' => 'hostname',
            'level' => 'DEBUG',
            'bubble' => false,
        ];
    }

    public function testServerLogHandlerValidConfiguration()
    {
        $configs = [
            [
                'handlers' => [
                    'foo' => array_merge(['type' => 'server_log'], $this->provideServerLogHandlerValidConfigurationParams()),
                ]
            ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
    }
}
