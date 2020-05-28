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
                        'type' => 'slack',
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
     * @dataProvider provideHandlersConfigurations
     */
    public function testHandlerValidConfiguration($config)
    {
        $config = $this->process([$config]);

        $this->assertArrayHasKey('handlers', $config);
    }

    public function provideHandlersConfigurations()
    {
        return
            [
                [
                    ['handlers' => $this->getHandlersConfigurations()]
                ]
            ];
    }

    private function getHandlersConfigurations()
    {
        $allConfigParams = $this->provideAllHandlersConfigurationParams();

        return [
            'stream' => [
                'type' => 'stream',
                'path' => $allConfigParams['path'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'file_permission' => $allConfigParams['file_permission'],
                'use_locking' => $allConfigParams['use_locking'],
            ],
            'console' => [
                'type' => 'console',
                'verbosity_levels' => $allConfigParams['verbosity_levels'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'console_formater_options' => $allConfigParams['console_formater_options'],
            ],
            'firephp' => [
                'type' => 'firephp',
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'browser_console' => [
                'type' => 'browser_console',
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'gelf' => [
                'type' => 'gelf',
                'publisher' => $allConfigParams['publisher'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'chromephp' => [
                'type' => 'chromephp',
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'rotating_file' => [
                'type' => 'rotating_file',
                'path' => $allConfigParams['path'],
                'max_files' => $allConfigParams['max_files'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'file_permission' => $allConfigParams['file_permission'],
                'filename_format' => $allConfigParams['filename_format'],
                'date_format' => $allConfigParams['date_format'],
            ],
            'mongo' => [
                'type' => 'mongo',
                'mongo' => $allConfigParams['mongo'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'elasticsearch' => [
                'type' => 'elasticsearch',
                'elasticsearch' => $allConfigParams['elasticsearch'],
                'index' => $allConfigParams['index'],
                'document_type' => $allConfigParams['document_type'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'redis' => [
                'type' => 'redis',
                'redis' => $allConfigParams['redis'],
            ],
            'predis' => [
                'type' => 'predis',
                'redis' => $allConfigParams['redis'],
            ],
//            'fingers_crossed' => [
//                'type' => 'fingers_crossed',
//                'handler' => $allConfigParams['handler'],
//                'action_level' => $allConfigParams['action_level'],
//                'excluded_http_codes' => $allConfigParams['excluded_http_codes'],
//                'buffer_size' => $allConfigParams['buffer_size'],
//                'stop_buffering' => $allConfigParams['stop_buffering'],
//                'passthru_level' => $allConfigParams['passthru_level'],
//                'bubble' => $allConfigParams['bubble'],
//            ],
//            'filter' => [
//                'type' => 'filter',
//                'handler' => $allConfigParams['handler'],
//                'accepted_levels' => $allConfigParams['accepted_levels'],
//                'min_level' => $allConfigParams['min_level'],
//                'max_level' => $allConfigParams['max_level'],
//                'bubble' => $allConfigParams['bubble'],
//            ],
            'buffer' => [
                'type' => 'buffer',
                'handler' => $allConfigParams['handler'],
                'buffer_size' => $allConfigParams['buffer_size'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'flush_on_overflow' => $allConfigParams['flush_on_overflow'],
            ],
            'deduplication' => [
                'type' => 'deduplication',
                'handler' => $allConfigParams['handler'],
                'store' => $allConfigParams['store'],
                'deduplication_level' => $allConfigParams['deduplication_level'],
                'time' => $allConfigParams['time'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'group' => [
                'type' => 'group',
                'members' => $allConfigParams['members'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'whatfailuregroup' => [
                'type' => 'whatfailuregroup',
                'members' => $allConfigParams['members'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'syslog' => [
                'type' => 'syslog',
                'ident' => $allConfigParams['ident'],
                'facility' => $allConfigParams['facility'],
                'logopts' => $allConfigParams['logopts'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'syslogudp' => [
                'type' => 'syslogudp',
                'host' => $allConfigParams['host'],
                'port' => $allConfigParams['port'],
                'facility' => $allConfigParams['facility'],
                'logopts' => $allConfigParams['logopts'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'ident' => $allConfigParams['ident'],
            ],
            'swift_mailer' => [
                'type' => 'swift_mailer',
                'from_email' => $allConfigParams['from_email'],
                'to_email' => $allConfigParams['to_email'],
                'subject' => $allConfigParams['subject'],
                'email_prototype' => $allConfigParams['email_prototype'],
                'content_type' => $allConfigParams['content_type'],
                'mailer' => $allConfigParams['mailer'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'lazy' => $allConfigParams['lazy'],
            ],
            'native_mailer' => [
                'type' => 'native_mailer',
                'from_email' => $allConfigParams['from_email'],
                'to_email' => $allConfigParams['to_email'],
                'subject' => $allConfigParams['subject'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'headers' => $allConfigParams['headers'],
            ],
            'socket' => [
                'type' => 'socket',
                'connection_string' => $allConfigParams['connection_string'],
                'timeout' => $allConfigParams['timeout'],
                'connection_timeout' => $allConfigParams['connection_timeout'],
                'persistent' => $allConfigParams['persistent'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'pushover' => [
                'type' => 'pushover',
                'token' => $allConfigParams['token'],
                'user' => $allConfigParams['user'],
                'title' => $allConfigParams['title'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'timeout' => $allConfigParams['timeout'],
                'connection_timeout' => $allConfigParams['connection_timeout'],
            ],
            'raven' => [
                'type' => 'raven',
                'dsn' => $allConfigParams['dsn'],
                'client_id' => $allConfigParams['client_id'],
                'release' => $allConfigParams['release'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'auto_log_stacks' => $allConfigParams['auto_log_stacks'],
                'environment' => $allConfigParams['environment'],
            ],
            'sentry' => [
                'type' => 'sentry',
                'dsn' => $allConfigParams['dsn'],
                'client_id' => $allConfigParams['client_id'],
                'release' => $allConfigParams['release'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'auto_log_stacks' => $allConfigParams['auto_log_stacks'],
                'environment' => $allConfigParams['environment'],
            ],
            'newrelic' => [
                'type' => 'newrelic',
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'app_name' => $allConfigParams['app_name'],
            ],
            'hipchat' => [
                'type' => 'hipchat',
                'token' => $allConfigParams['token'],
                'room' => $allConfigParams['room'],
                'notify' => $allConfigParams['notify'],
                'nickname' => $allConfigParams['nickname'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'use_ssl' => $allConfigParams['use_ssl'],
                'message_format' => $allConfigParams['message_format'],
                'host' => $allConfigParams['host'],
                'api_version' => $allConfigParams['api_version'],
                'timeout' => $allConfigParams['timeout'],
                'connection_timeout' => $allConfigParams['connection_timeout'],
            ],
            'slack' => [
                'type' => 'slack',
                'token' => $allConfigParams['token'],
                'channel' => $allConfigParams['channel'],
                'bot_name' => $allConfigParams['bot_name'],
                'icon_emoji' => $allConfigParams['icon_emoji'],
                'use_attachment' => $allConfigParams['use_attachment'],
                'use_short_attachment' => $allConfigParams['use_short_attachment'],
                'include_extra' => $allConfigParams['include_extra'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'timeout' => $allConfigParams['timeout'],
                'connection_timeout' => $allConfigParams['connection_timeout'],
            ],
            'slackwebhook' => [
                'type' => 'slackwebhook',
                'webhook_url' => $allConfigParams['webhook_url'],
                'channel' => $allConfigParams['channel'],
                'bot_name' => $allConfigParams['bot_name'],
                'icon_emoji' => $allConfigParams['icon_emoji'],
                'use_attachment' => $allConfigParams['use_attachment'],
                'use_short_attachment' => $allConfigParams['use_short_attachment'],
                'include_extra' => $allConfigParams['include_extra'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'slackbot' => [
                'type' => 'slackbot',
                'team' => $allConfigParams['team'],
                'token' => $allConfigParams['token'],
                'channel' => $allConfigParams['channel'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'cube' => [
                'type' => 'cube',
                'url' => $allConfigParams['url'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'amqp' => [
                'type' => 'amqp',
                'exchange' => $allConfigParams['exchange'],
                'exchange_name' => $allConfigParams['exchange_name'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'error_log' => [
                'type' => 'error_log',
                'message_type' => $allConfigParams['message_type'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'null' => [
                'type' => 'null',
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'test' => [
                'type' => 'test',
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'deb' => [
                'type' => 'debug',
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'loggly' => [
                'type' => 'loggly',
                'token' => $allConfigParams['token'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'tags' => $allConfigParams['tags'],
            ],
            'logentries' => [
                'type' => 'logentries',
                'token' => $allConfigParams['token'],
                'use_ssl' => $allConfigParams['use_ssl'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
                'timeout' => $allConfigParams['timeout'],
                'connection_timeout' => $allConfigParams['connection_timeout'],
            ],
            'insightops' => [
                'type' => 'insightops',
                'token' => $allConfigParams['token'],
                'region' => $allConfigParams['region'],
                'use_ssl' => $allConfigParams['use_ssl'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
            'flowdock' => [
                'type' => 'flowdock',
                'token' => $allConfigParams['token'],
                'source' => $allConfigParams['source'],
                'from_email' => $allConfigParams['from_email'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ],
//            'rollbar' => [
//                'type' => 'rollbar',
//                'id' => $allConfigParams['id'],
//                'token' => $allConfigParams['token'],
//                'config' => $allConfigParams['config'],
//                'level' => $allConfigParams['level'],
//                'bubble' => $allConfigParams['bubble'],
//            ]
            'server_log' => [
                'type' => 'server_log',
                'host' => $allConfigParams['host'],
                'level' => $allConfigParams['level'],
                'bubble' => $allConfigParams['bubble'],
            ]
        ];
    }

    private function provideAllHandlersConfigurationParams()
    {
        return [
            'accepted_levels' => ['DEBUG'],
            'action_level' => 'DEBUG',
            'activation_strategy' => 'DEBUG',
            'api_version' => 'v2',
            'app_name' => 'app_name',
            'auto_log_stacks' => true,
            'bubble' => false,
            'bot_name' => 'bot_name',
            'buffer_size' => 0,
            'channel' => '#channel_name',
            'client_id' => 'client_id',
            'config' => ['config' => 'config'],
            'connection_string' => 'connection_string',
            'connection_timeout' => 0.5,
            'console_formater_options' => [],
            'content_type' => 'text/plain',
            'date_format' => 'Y-m-d',
            'deduplication_level' => 'DEBUG',
            'document_type' => 'logs',
            'dsn' => 'dsn_connection_string',
            'elasticsearch' => 'id',
            'email_prototype' => 'service_message_id',
            'environment' => 'dev',
            'exchange' => 'service_id',
            'exchange_name' => 'log',
            'excluded_404s' => ['^/.*'],
            'excluded_http_codes' => [404],
            'facility' => 'user',
            'filename_format' => 'filename_format',
            'file_permission' => 777,
            'flush_on_overflow' =>  true,
            'from_email' => 'fromemail@test.com',
            'handler' => 'handler_name',
            'headers' => ['Foo: Bar'],
            'host' => 'hostname',
            'icon_emoji' => ':icon_emoji:',
            'id' => 'id',
            'ident' => 'ident',
            'include_extra' => true,
            'index' => 'monolog',
            'lazy' => false,
            'level' => 'DEBUG',
            'logopts' => 'LOGPID',
            'mailer' =>  'mailer',
            'max_files' => 0,
            'max_level' => 'DEBUG',
            'message_format' => 'html',
            'message_type' => '1',
            'min_level' => 'DEBUG',
            'members' => ['foo'],
            'mongo' => 'id',
            'nickname' => 'nickname',
            'notify' => true,
            'path' => '/a/path',
            'passthru_level' => 'DEBUG',
            'persistent' => true,
            'port' => 514,
            'publisher' => 'id',
            'redis' => ['id' => 'id'],
            'release' => '1.0.1',
            'region' => 'eu',
            'room' => 'room_id',
            'source' => 'source_id',
            'stop_buffering' => false,
            'store' => '/',
            'subject' => 'subject',
            'tags' => ['a_tag'],
            'team' => 'team',
            'time' => 3600,
            'timeout' => 1.2,
            'title' => 'title',
            'token' => 'api_token',
            'to_email' => 'toemail@test.com',
            'url' => 'http://localhost',
            'user' => 'user_id',
            'use_attachment' => false,
            'use_locking' => true,
            'use_short_attachment' => true,
            'use_ssl' => false,
            'verbosity_levels' => ['DEBUG'],
            'webhook_url' => 'http://localhost',
        ];
    }

    /**
     * @dataProvider provideHandlersInvalidConfigurations
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testHandlerInvalidConfiguration($config)
    {
        $this->process([$config]);
    }

    public function provideHandlersInvalidConfigurations()
    {
        $madatoryParams = [
            'stream' => ['path'],
            'gelf' => ['publisher'],
            'rotating_file' => ['path'],
            'mongo' => ['id', 'host', 'pass'],
            'elasticsearch' => ['elasticsearch'],
            'redis' => ['redis'],
            'predis' => ['redis'],
            'fingers_crossed' => ['handler'],
            'filter' => ['handler'],
            'buffer' => ['handler'],
            'deduplication' => ['handler'],
            'group' => ['members'],
            'whatfailuregroup' => ['members'],
            'syslog' => ['ident'],
            'syslogudp' => ['host'],
            'swift_mailer' => ['from_email', 'to_email', 'subject'],
            'native_mailer' => ['from_email', 'to_email', 'subject'],
            'socket' => ['connection_string'],
            'pushover' => ['token', 'user'],
            'raven' => ['dsn', 'client_id'],
            'sentry' => ['dsn', 'client_id'],
            'hipchat' => ['token', 'room'],
            'slack' => ['token', 'channel'],
            'slackwebhook' => ['webhook_url', 'channel'],
            'slackbot' => ['team', 'token', 'channel'],
            'cube' => ['url'],
            'amqp' => ['exchange'],
            'loggly' => ['token'],
            'logentries' => ['token'],
            'insightops' => ['token', 'region'],
            'flowdock' => ['token', 'source', 'from_email'],
            'rollbar' => ['id', 'token'],
            'server_log' => ['host'],
        ];

        $handlersConfigurations = $this->getHandlersConfigurations();
        $handlersConfigurationParams = $this->provideAllHandlersConfigurationParams();

        $configs = [];

        foreach ($handlersConfigurations as $handlerType => $handler) {
            $mandatoryParams = array_key_exists($handlerType, $madatoryParams) ? $madatoryParams[$handlerType] : [];
            $invalidParams = array_diff_key($handlersConfigurationParams, $handler);
            $handlerMandatoryParams = array_intersect_key($handlersConfigurationParams, array_flip($mandatoryParams));
            foreach($invalidParams as $invalidParamKey => $invalidParamValue) {
                $configs[] = [
                    [
                        'handlers' => [
                            $handlerType => array_merge([
                                'type' => $handler['type'],
                                $invalidParamKey => $invalidParamValue
                            ], $handlerMandatoryParams)
                        ]
                    ]
                ];
            }
        }

        return $configs;
    }

//    /**
//     * @dataProvider provideFingersCrossedHandlerConfigurationInvalidParams
//     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
//     */
//    public function testFingersCrossedHandlerInvalidConfiguration($param, $value)
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(['type' => 'fingers_crossed'], ['handler' => 'handler_name', $param => $value]),
//                ]
//            ],
//        ];
//
//        $this->process($configs);
//    }
//
//    public function provideFingersCrossedHandlerConfigurationInvalidParams()
//    {
//        return array_diff_key(
//            $this->provideAllHandlersConfigurationParams(),
//            $this->provideFingersCrossedHandlerValidConfigurationParams()
//        );
//    }
//
//    private function provideFingersCrossedHandlerValidConfigurationParams()
//    {
//        return [
//            'handler' => 'handler_name',
//            'action_level' => 'DEBUG',
//            'activation_strategy' => 'DEBUG',
//            'excluded_404s' => ['^/.*'],
//            'excluded_http_codes' => [404],
//            'buffer_size' => 0,
//            'stop_buffering' => false,
//            'passthru_level' => 'DEBUG',
//            'bubble' => false,
//        ];
//    }
//
//    public function testFingersCrossedHandlerWithActionLevelExcluded404sConfiguration()
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(
//                        ['type' => 'fingers_crossed'],
//                        [
//                            'handler' => 'handler_name',
//                            'action_level' => 'DEBUG',
//                            'excluded_http_codes' => [404],
//                            'buffer_size' => 0,
//                            'stop_buffering' => false,
//                            'passthru_level' => 'DEBUG',
//                            'bubble' => false,
//                        ]
//                    ),
//                ]
//            ],
//        ];
//
//        $config = $this->process($configs);
//
//        $this->assertArrayHasKey('handlers', $config);
//    }
//
//    public function testFingersCrossedHandlerWithActionLevelExcludedHttpCodesConfiguration()
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(
//                        ['type' => 'fingers_crossed'],
//                        [
//                            'handler' => 'handler_name',
//                            'action_level' => 'DEBUG',
//                            'excluded_404s' => ['^/.*'],
//                            'buffer_size' => 0,
//                            'stop_buffering' => false,
//                            'passthru_level' => 'DEBUG',
//                            'bubble' => false,
//                        ]
//                    ),
//                ]
//            ],
//        ];
//
//        $config = $this->process($configs);
//
//        $this->assertArrayHasKey('handlers', $config);
//    }
//
//    public function testFingersCrossedHandlerWithActivationStrategyConfiguration()
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(
//                        ['type' => 'fingers_crossed'],
//                        [
//                            'handler' => 'handler_name',
//                            'activation_strategy' => 'DEBUG',
//                            'buffer_size' => 0,
//                            'stop_buffering' => false,
//                            'passthru_level' => 'DEBUG',
//                            'bubble' => false,
//                        ]
//                    ),
//                ]
//            ],
//        ];
//
//        $config = $this->process($configs);
//
//        $this->assertArrayHasKey('handlers', $config);
//    }
//
//    /**
//     * @dataProvider provideFilterHandlerConfigurationInvalidParams
//     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
//     */
//    public function testFilterHandlerInvalidConfiguration($param, $value)
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(['type' => 'filter'], ['handler' => 'handler_name', $param => $value]),
//                ]
//            ],
//        ];
//
//        $this->process($configs);
//    }
//
//    public function provideFilterHandlerConfigurationInvalidParams()
//    {
//        return array_diff_key(
//            $this->provideAllHandlersConfigurationParams(),
//            $this->provideFilterHandlerValidConfigurationParams()
//        );
//    }
//
//    private function provideFilterHandlerValidConfigurationParams()
//    {
//        return [
//            'handler' => 'handler_name',
//            'accepted_levels' => ['DEBUG'],
//            'min_level' => 'DEBUG',
//            'max_level' => 'DEBUG',
//            'bubble' => false,
//        ];
//    }
//
//    public function testFilterHandlerWithAcceptedLevelsValidConfiguration()
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(['type' => 'filter'], [
//                        'handler' => 'handler_name',
//                        'accepted_levels' => ['DEBUG'],
//                        'bubble' => false,
//                    ]),
//                ]
//            ],
//        ];
//
//        $config = $this->process($configs);
//
//        $this->assertArrayHasKey('handlers', $config);
//    }
//
//    public function testFilterHandlerWithMinMaxLevelsValidConfiguration()
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(['type' => 'filter'], [
//                        'handler' => 'handler_name',
//                        'min_level' => 'DEBUG',
//                        'max_level' => 'DEBUG',
//                        'bubble' => false,
//                    ]),
//                ]
//            ],
//        ];
//
//        $config = $this->process($configs);
//
//        $this->assertArrayHasKey('handlers', $config);
//    }
//
//    /**
//     * @dataProvider provideRollbarHandlerConfigurationInvalidParams
//     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
//     */
//    public function testRollbarHandlerInvalidConfiguration($param, $value)
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(['type' => 'rollbar'], ['id' => 'id', 'token' => 'api_token', $param => $value]),
//                ]
//            ],
//        ];
//
//        $this->process($configs);
//    }
//
//    public function provideRollbarHandlerConfigurationInvalidParams()
//    {
//        return array_diff_key(
//            $this->provideAllHandlersConfigurationParams(),
//            $this->provideRollbarHandlerValidConfigurationParams()
//        );
//    }
//
//    private function provideRollbarHandlerValidConfigurationParams()
//    {
//        return [
//            'id' => 'id',
//            'token' => 'api_token',
//            'config' => ['config' => 'config'],
//            'level' => 'DEBUG',
//            'bubble' => false,
//        ];
//    }
//
//    public function testRollbarHandlerValidIdConfiguration()
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(
//                        ['type' => 'rollbar'],
//                        [
//                            'id' => 'id',
//                            'config' => ['config' => 'config'],
//                            'level' => 'DEBUG',
//                            'bubble' => false,
//                        ]
//                    )
//                ]
//            ],
//        ];
//
//        $config = $this->process($configs);
//
//        $this->assertArrayHasKey('handlers', $config);
//    }
//
//    public function testRollbarHandlerValidTokenConfiguration()
//    {
//        $configs = [
//            [
//                'handlers' => [
//                    'foo' => array_merge(
//                        ['type' => 'rollbar'],
//                        [
//                            'token' => 'api_token',
//                            'config' => ['config' => 'config'],
//                            'level' => 'DEBUG',
//                            'bubble' => false,
//                        ]
//                    )
//                ]
//            ],
//        ];
//
//        $config = $this->process($configs);
//
//        $this->assertArrayHasKey('handlers', $config);
//    }
}
