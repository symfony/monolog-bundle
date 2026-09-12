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

use Monolog\Handler\ElasticaHandler;
use Monolog\Handler\ElasticsearchHandler;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\MongoDBHandler;
use Monolog\Handler\RollbarHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\UidProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\AddHandlersToManagerPass;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\LoggerChannelPass;
use Symfony\Bundle\MonologBundle\DependencyInjection\FormatterConfigurator;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures\AsMonologProcessor\FooProcessorWithPriority;
use Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures\AsMonologProcessor\RedeclareMethodProcessor;
use Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures\ServiceWithChannel;
use Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures\ServiceWithChannelOnArgument;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;

class MonologExtensionTest extends DependencyInjectionTestCase
{
    public function testLoadWithDefault()
    {
        $container = $this->getContainer([['handlers' => ['main' => ['type' => 'stream']]]]);

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.main')]);

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\StreamHandler::class);
        $this->assertDICConstructorArguments($handler, ['%kernel.logs_dir%/%kernel.environment%.log', 'DEBUG', true, null, false]);
        $this->assertDICDefinitionMethodCallAt(0, $handler, 'pushProcessor', [new Reference('monolog.processor.psr_log_message')]);
    }

    public function testLoadWithCustomValues()
    {
        $container = $this->getContainer([['handlers' => [
            'custom' => ['type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR', 'file_permission' => '0666', 'use_locking' => true],
        ]]]);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.custom')]);

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\StreamHandler::class);
        $this->assertDICConstructorArguments($handler, ['/tmp/symfony.log', 'ERROR', false, 0666, true]);
    }

    public function testStreamHandlerWithSubNodeConfig()
    {
        $container = $this->getContainer([['handlers' => [
            'custom' => ['type' => 'stream', 'stream' => ['path' => '/tmp/sub.log', 'file_permission' => '0640', 'use_locking' => true], 'level' => 'WARNING', 'bubble' => false],
        ]]]);

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\StreamHandler::class);
        $this->assertDICConstructorArguments($handler, ['/tmp/sub.log', 'WARNING', false, 0640, true]);
    }

    public function testStreamHandlerTypeInferredFromSubNode()
    {
        $container = $this->getContainer([['handlers' => [
            'custom' => ['stream' => ['path' => '/tmp/inferred.log']],
        ]]]);

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\StreamHandler::class);
        $this->assertDICConstructorArguments($handler, ['/tmp/inferred.log', 'DEBUG', true, null, false]);
    }

    #[DataProvider('provideConvertedHandlersWithSubNodeConfig')]
    public function testConvertedHandlersWithSubNodeConfig(string $name, array $handlers, string $expectedClass, array $expectedArgs, array $expectedMethodCalls = []): void
    {
        $container = $this->getContainer([['handlers' => $handlers]], $this->handlerServiceDependencies());

        $handler = $container->getDefinition('monolog.handler.'.$name);
        $this->assertDICDefinitionClass($handler, $expectedClass);
        $this->assertDICConstructorArguments($handler, $expectedArgs);

        $methodCalls = $handler->getMethodCalls();
        $this->assertCount(\count($expectedMethodCalls), $methodCalls);
        foreach ($expectedMethodCalls as $pos => [$method, $args]) {
            $this->assertDICDefinitionMethodCallAt($pos, $handler, $method, $args);
        }
    }

    public static function provideConvertedHandlersWithSubNodeConfig(): iterable
    {
        $nested = static fn (string $name) => ['type' => 'stream', 'path' => '/tmp/'.$name.'.log'];
        $psr3 = [new Reference('monolog.processor.psr_log_message')];

        yield 'rotating_file' => ['rotating', ['rotating' => [
            'type' => 'rotating_file',
            'level' => 'WARNING',
            'rotating_file' => [
                'path' => '/tmp/rot.log',
                'max_files' => 5,
                'file_permission' => '0600',
                'use_locking' => true,
                'filename_format' => '{filename}-{date}',
                'date_format' => 'Y-m-d',
            ],
        ]], \Monolog\Handler\RotatingFileHandler::class, ['/tmp/rot.log', 5, 'WARNING', true, 0600, true], [
            ['pushProcessor', $psr3],
            ['setFilenameFormat', ['{filename}-{date}', 'Y-m-d']],
        ]];

        yield 'socket' => ['socket', ['socket' => [
            'type' => 'socket',
            'socket' => [
                'connection_string' => 'localhost:9000',
                'timeout' => 2,
                'connection_timeout' => 0.7,
                'persistent' => false,
            ],
        ]], \Monolog\Handler\SocketHandler::class, ['localhost:9000', 'DEBUG', true], [
            ['pushProcessor', $psr3],
            ['setTimeout', [2]],
            ['setConnectionTimeout', [0.7]],
            ['setPersistent', [false]],
        ]];

        yield 'syslog' => ['syslog', ['syslog' => [
            'type' => 'syslog',
            'syslog' => ['ident' => 'myapp', 'facility' => 'local0', 'logopts' => 1],
        ]], \Monolog\Handler\SyslogHandler::class, ['myapp', 'local0', 'DEBUG', true, 1], [
            ['pushProcessor', $psr3],
        ]];

        yield 'syslogudp' => ['syslogudp', ['syslogudp' => [
            'type' => 'syslogudp',
            'syslogudp' => [
                'host' => '127.0.0.2',
                'port' => 1514,
                'facility' => 'user',
                'ident' => 'php',
                'rfc' => SyslogUdpHandler::RFC3164,
            ],
        ]], \Monolog\Handler\SyslogUdpHandler::class, ['127.0.0.2', 1514, 'user', 'DEBUG', true, 'php', SyslogUdpHandler::RFC3164], [
            ['pushProcessor', $psr3],
        ]];

        yield 'cube' => ['cube', ['cube' => [
            'type' => 'cube',
            'cube' => ['url' => 'udp://127.0.0.1:1180'],
        ]], \Monolog\Handler\CubeHandler::class, ['udp://127.0.0.1:1180', 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'error_log' => ['error_log', ['error_log' => [
            'type' => 'error_log',
            'error_log' => ['message_type' => 4, 'expand_newlines' => true],
        ]], \Monolog\Handler\ErrorLogHandler::class, [4, 'DEBUG', true, true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'server_log' => ['server_log', ['server_log' => [
            'type' => 'server_log',
            'server_log' => ['host' => '0:9911'],
        ]], \Symfony\Bridge\Monolog\Handler\ServerLogHandler::class, ['0:9911', 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'amqp' => ['amqp', ['amqp' => [
            'type' => 'amqp',
            'amqp' => ['exchange' => 'my.exchange', 'exchange_name' => 'logs'],
        ]], \Monolog\Handler\AmqpHandler::class, [new Reference('my.exchange'), 'logs', 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'logentries' => ['logentries', ['logentries' => [
            'type' => 'logentries',
            'logentries' => ['token' => 'mytoken', 'use_ssl' => false],
        ]], \Monolog\Handler\LogEntriesHandler::class, ['mytoken', false, 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'loggly' => ['loggly', ['loggly' => [
            'type' => 'loggly',
            'loggly' => ['token' => 'mytoken', 'tags' => ['foo', 'bar']],
        ]], \Monolog\Handler\LogglyHandler::class, ['mytoken', 'DEBUG', true], [
            ['pushProcessor', $psr3],
            ['setTag', ['foo,bar']],
        ]];

        yield 'insightops' => ['insightops', ['insightops' => [
            'type' => 'insightops',
            'insightops' => ['token' => 'mytoken', 'region' => 'eu', 'use_ssl' => false],
        ]], \Monolog\Handler\InsightOpsHandler::class, ['mytoken', 'eu', false, 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'flowdock' => ['flowdock', ['flowdock' => [
            'type' => 'flowdock',
            'flowdock' => ['token' => 'mytoken', 'source' => 'src', 'from_email' => 'f@example.com'],
        ]], \Monolog\Handler\FlowdockHandler::class, ['mytoken', 'DEBUG', true], [
            ['pushProcessor', $psr3],
            ['setFormatter', [new Reference('monolog.flowdock.formatter.'.sha1('src|f@example.com'))]],
        ]];

        yield 'pushover' => ['pushover', ['pushover' => [
            'type' => 'pushover',
            'level' => 'ERROR',
            'pushover' => ['token' => 'token1', 'user' => 'user1', 'title' => 'My Title'],
        ]], \Monolog\Handler\PushoverHandler::class, ['token1', 'user1', 'My Title', 'ERROR', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'telegram' => ['telegram', ['telegram' => [
            'type' => 'telegram',
            'telegram' => ['token' => 'bot-token', 'channel' => '-100', 'parse_mode' => 'HTML'],
        ]], \Monolog\Handler\TelegramBotHandler::class, ['bot-token', '-100', 'DEBUG', true, 'HTML', null, null, false, false, null], [
            ['pushProcessor', $psr3],
        ]];

        yield 'rollbar' => ['rollbar', ['rollbar' => [
            'type' => 'rollbar',
            'rollbar' => ['token' => 'TOKEN'],
        ]], RollbarHandler::class, [new Reference('monolog.rollbar.notifier.'.sha1(json_encode(['access_token' => 'TOKEN']))), 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'newrelic' => ['newrelic', ['newrelic' => [
            'type' => 'newrelic',
            'newrelic' => ['app_name' => 'myapp'],
        ]], \Monolog\Handler\NewRelicHandler::class, ['DEBUG', true, 'myapp'], [
            ['pushProcessor', $psr3],
        ]];

        yield 'console' => ['console', ['console' => [
            'type' => 'console',
            'console' => [
                'verbosity_levels' => [500, 400, 300, 250, 200],
                'console_formatter_options' => [],
                'interactive_only' => false,
            ],
        ]], \Symfony\Bridge\Monolog\Handler\ConsoleHandler::class, [
            null,
            true,
            [
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_QUIET => 500,
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_NORMAL => 400,
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE => 300,
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE => 250,
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_DEBUG => 200,
            ],
            [],
            false,
        ], [
            ['pushProcessor', $psr3],
        ]];

        yield 'firephp' => ['firephp', ['firephp' => [
            'type' => 'firephp',
            'level' => 'WARNING',
        ]], \Symfony\Bridge\Monolog\Handler\FirePHPHandler::class, ['WARNING', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'chromephp' => ['chromephp', ['chromephp' => [
            'type' => 'chromephp',
            'level' => 'WARNING',
        ]], \Symfony\Bridge\Monolog\Handler\ChromePhpHandler::class, ['WARNING', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'slack' => ['slack', ['slack' => [
            'type' => 'slack',
            'slack' => [
                'token' => 'slack-token',
                'channel' => '#logs',
                'exclude_fields' => ['field1'],
            ],
        ]], \Monolog\Handler\SlackHandler::class, ['slack-token', '#logs', 'Monolog', true, null, 'DEBUG', true, false, false, ['field1']], [
            ['pushProcessor', $psr3],
        ]];

        yield 'slackwebhook' => ['slackwebhook', ['slackwebhook' => [
            'type' => 'slackwebhook',
            'slackwebhook' => [
                'webhook_url' => 'https://hooks.slack.com/services/...',
                'channel' => '#logs',
                'exclude_fields' => ['field1'],
            ],
        ]], \Monolog\Handler\SlackWebhookHandler::class, ['https://hooks.slack.com/services/...', '#logs', 'Monolog', true, null, false, false, 'DEBUG', true, ['field1']], [
            ['pushProcessor', $psr3],
        ]];

        yield 'gelf' => ['gelf', ['gelf' => [
            'type' => 'gelf',
            'gelf' => ['publisher' => ['id' => 'gelf.publisher']],
        ]], \Monolog\Handler\GelfHandler::class, [new Reference('gelf.publisher'), 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'fingers_crossed' => ['fingers_crossed', [
            'fingers_crossed' => [
                'type' => 'fingers_crossed',
                'fingers_crossed' => ['handler' => 'nested', 'buffer_size' => 30],
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\FingersCrossedHandler::class, [
            new Reference('monolog.handler.nested'),
            new Definition(\Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy::class, ['WARNING']),
            30,
            true,
            true,
            null,
        ]];

        yield 'filter' => ['filter', [
            'filter' => [
                'type' => 'filter',
                'filter' => ['handler' => 'nested', 'accepted_levels' => ['WARNING', 'ERROR']],
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\FilterHandler::class, [
            new Reference('monolog.handler.nested'),
            ['WARNING', 'ERROR'],
            'EMERGENCY',
            true,
        ]];

        yield 'buffer' => ['buffer', [
            'buffer' => [
                'type' => 'buffer',
                'buffer' => ['handler' => 'nested', 'buffer_size' => 5],
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\BufferHandler::class, [
            new Reference('monolog.handler.nested'),
            5,
            'DEBUG',
            true,
            false,
        ]];

        yield 'deduplication' => ['deduplication', [
            'deduplication' => [
                'type' => 'deduplication',
                'deduplication' => ['handler' => 'nested', 'time' => 60],
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\DeduplicationHandler::class, [
            new Reference('monolog.handler.nested'),
            '%kernel.cache_dir%/monolog_dedup_'.sha1('monolog.handler.deduplication'),
            \Monolog\Level::Error->value,
            60,
            true,
        ]];

        yield 'sampling' => ['sampling', [
            'sampling' => [
                'type' => 'sampling',
                'sampling' => ['handler' => 'nested', 'factor' => 10],
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\SamplingHandler::class, [
            new Reference('monolog.handler.nested'),
            10,
        ]];

        yield 'group' => ['group', [
            'group' => ['type' => 'group', 'group' => ['members' => ['a', 'b']]],
            'a' => $nested('a'),
            'b' => $nested('b'),
        ], \Monolog\Handler\GroupHandler::class, [
            [new Reference('monolog.handler.a'), new Reference('monolog.handler.b')],
            true,
        ]];

        yield 'whatfailuregroup' => ['whatfailuregroup', [
            'whatfailuregroup' => ['type' => 'whatfailuregroup', 'whatfailuregroup' => ['members' => ['a', 'b']]],
            'a' => $nested('a'),
            'b' => $nested('b'),
        ], \Monolog\Handler\WhatFailureGroupHandler::class, [
            [new Reference('monolog.handler.a'), new Reference('monolog.handler.b')],
            true,
        ]];

        yield 'fallbackgroup' => ['fallbackgroup', [
            'fallbackgroup' => ['type' => 'fallbackgroup', 'fallbackgroup' => ['members' => ['a', 'b']]],
            'a' => $nested('a'),
            'b' => $nested('b'),
        ], \Monolog\Handler\FallbackGroupHandler::class, [
            [new Reference('monolog.handler.a'), new Reference('monolog.handler.b')],
            true,
        ]];

        yield 'native_mailer' => ['native_mailer', ['native_mailer' => [
            'type' => 'native_mailer',
            'native_mailer' => [
                'from_email' => 'f@example.com',
                'to_email' => ['t@example.com'],
                'subject' => 'Subject',
                'headers' => ['Foo: bar'],
                'parameters' => ['--foo'],
            ],
        ]], \Monolog\Handler\NativeMailerHandler::class, [['t@example.com'], 'Subject', 'f@example.com', 'DEBUG', true], [
            ['pushProcessor', $psr3],
            ['addHeader', [['Foo: bar']]],
            ['addParameter', [['--foo']]],
        ]];

        $symfonyMailerPrototype = (new Definition(\Symfony\Component\Mime\Email::class))
            ->setPublic(false)
            ->addMethodCall('from', ['f@example.com'])
            ->addMethodCall('to', ['t@example.com'])
            ->addMethodCall('subject', ['Subject']);

        yield 'symfony_mailer' => ['symfony_mailer', ['symfony_mailer' => [
            'type' => 'symfony_mailer',
            'symfony_mailer' => [
                'from_email' => 'f@example.com',
                'to_email' => ['t@example.com'],
                'subject' => 'Subject',
            ],
        ]], \Symfony\Bridge\Monolog\Handler\MailerHandler::class, [
            new Reference('mailer.mailer'),
            $symfonyMailerPrototype,
            'DEBUG',
            true,
        ], [
            ['pushProcessor', $psr3],
        ]];
    }

    #[DataProvider('provideConvertedHandlersWithLegacyFlatConfig')]
    public function testConvertedHandlersWithLegacyFlatConfig(string $name, array $handlers, string $expectedClass, array $expectedArgs, array $expectedMethodCalls = []): void
    {
        $container = $this->getContainer([['handlers' => $handlers]], $this->handlerServiceDependencies());

        $handler = $container->getDefinition('monolog.handler.'.$name);
        $this->assertDICDefinitionClass($handler, $expectedClass);
        $this->assertDICConstructorArguments($handler, $expectedArgs);

        $methodCalls = $handler->getMethodCalls();
        $this->assertCount(\count($expectedMethodCalls), $methodCalls);
        foreach ($expectedMethodCalls as $pos => [$method, $args]) {
            $this->assertDICDefinitionMethodCallAt($pos, $handler, $method, $args);
        }
    }

    public static function provideConvertedHandlersWithLegacyFlatConfig(): iterable
    {
        $nested = static fn (string $name) => ['type' => 'stream', 'path' => '/tmp/'.$name.'.log'];
        $psr3 = [new Reference('monolog.processor.psr_log_message')];

        yield 'rotating_file' => ['rotating', ['rotating' => [
            'type' => 'rotating_file',
            'level' => 'WARNING',
            'path' => '/tmp/rot.log',
            'max_files' => 5,
            'file_permission' => '0600',
            'use_locking' => true,
            'filename_format' => '{filename}-{date}',
            'date_format' => 'Y-m-d',
        ]], \Monolog\Handler\RotatingFileHandler::class, ['/tmp/rot.log', 5, 'WARNING', true, 0600, true], [
            ['pushProcessor', $psr3],
            ['setFilenameFormat', ['{filename}-{date}', 'Y-m-d']],
        ]];

        yield 'socket' => ['socket', ['socket' => [
            'type' => 'socket',
            'connection_string' => 'localhost:9000',
            'timeout' => 2,
            'connection_timeout' => 0.7,
            'persistent' => false,
        ]], \Monolog\Handler\SocketHandler::class, ['localhost:9000', 'DEBUG', true], [
            ['pushProcessor', $psr3],
            ['setTimeout', [2]],
            ['setConnectionTimeout', [0.7]],
            ['setPersistent', [false]],
        ]];

        yield 'syslog' => ['syslog', ['syslog' => [
            'type' => 'syslog',
            'ident' => 'myapp',
            'facility' => 'local0',
            'logopts' => 1,
        ]], \Monolog\Handler\SyslogHandler::class, ['myapp', 'local0', 'DEBUG', true, 1], [
            ['pushProcessor', $psr3],
        ]];

        yield 'syslogudp' => ['syslogudp', ['syslogudp' => [
            'type' => 'syslogudp',
            'host' => '127.0.0.2',
            'port' => 1514,
            'facility' => 'user',
            'ident' => 'php',
            'rfc' => SyslogUdpHandler::RFC3164,
        ]], \Monolog\Handler\SyslogUdpHandler::class, ['127.0.0.2', 1514, 'user', 'DEBUG', true, 'php', SyslogUdpHandler::RFC3164], [
            ['pushProcessor', $psr3],
        ]];

        yield 'cube' => ['cube', ['cube' => [
            'type' => 'cube',
            'url' => 'udp://127.0.0.1:1180',
        ]], \Monolog\Handler\CubeHandler::class, ['udp://127.0.0.1:1180', 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'error_log' => ['error_log', ['error_log' => [
            'type' => 'error_log',
            'message_type' => 4,
            'expand_newlines' => true,
        ]], \Monolog\Handler\ErrorLogHandler::class, [4, 'DEBUG', true, true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'server_log' => ['server_log', ['server_log' => [
            'type' => 'server_log',
            'host' => '0:9911',
        ]], \Symfony\Bridge\Monolog\Handler\ServerLogHandler::class, ['0:9911', 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'amqp' => ['amqp', ['amqp' => [
            'type' => 'amqp',
            'exchange' => 'my.exchange',
            'exchange_name' => 'logs',
        ]], \Monolog\Handler\AmqpHandler::class, [new Reference('my.exchange'), 'logs', 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'logentries' => ['logentries', ['logentries' => [
            'type' => 'logentries',
            'token' => 'mytoken',
            'use_ssl' => false,
        ]], \Monolog\Handler\LogEntriesHandler::class, ['mytoken', false, 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'loggly' => ['loggly', ['loggly' => [
            'type' => 'loggly',
            'token' => 'mytoken',
            'tags' => ['foo', 'bar'],
        ]], \Monolog\Handler\LogglyHandler::class, ['mytoken', 'DEBUG', true], [
            ['pushProcessor', $psr3],
            ['setTag', ['foo,bar']],
        ]];

        yield 'insightops' => ['insightops', ['insightops' => [
            'type' => 'insightops',
            'token' => 'mytoken',
            'region' => 'eu',
            'use_ssl' => false,
        ]], \Monolog\Handler\InsightOpsHandler::class, ['mytoken', 'eu', false, 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'flowdock' => ['flowdock', ['flowdock' => [
            'type' => 'flowdock',
            'token' => 'mytoken',
            'source' => 'src',
            'from_email' => 'f@example.com',
        ]], \Monolog\Handler\FlowdockHandler::class, ['mytoken', 'DEBUG', true], [
            ['pushProcessor', $psr3],
            ['setFormatter', [new Reference('monolog.flowdock.formatter.'.sha1('src|f@example.com'))]],
        ]];

        yield 'pushover' => ['pushover', ['pushover' => [
            'type' => 'pushover',
            'level' => 'ERROR',
            'token' => 'token1',
            'user' => 'user1',
            'title' => 'My Title',
        ]], \Monolog\Handler\PushoverHandler::class, ['token1', 'user1', 'My Title', 'ERROR', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'telegram' => ['telegram', ['telegram' => [
            'type' => 'telegram',
            'token' => 'bot-token',
            'channel' => '-100',
            'parse_mode' => 'HTML',
        ]], \Monolog\Handler\TelegramBotHandler::class, ['bot-token', '-100', 'DEBUG', true, 'HTML', null, null, false, false, null], [
            ['pushProcessor', $psr3],
        ]];

        yield 'rollbar' => ['rollbar', ['rollbar' => [
            'type' => 'rollbar',
            'token' => 'TOKEN',
        ]], RollbarHandler::class, [new Reference('monolog.rollbar.notifier.'.sha1(json_encode(['access_token' => 'TOKEN']))), 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'newrelic' => ['newrelic', ['newrelic' => [
            'type' => 'newrelic',
            'app_name' => 'myapp',
        ]], \Monolog\Handler\NewRelicHandler::class, ['DEBUG', true, 'myapp'], [
            ['pushProcessor', $psr3],
        ]];

        yield 'console' => ['console', ['console' => [
            'type' => 'console',
            'verbosity_levels' => [500, 400, 300, 250, 200],
            'console_formatter_options' => [],
            'interactive_only' => false,
        ]], \Symfony\Bridge\Monolog\Handler\ConsoleHandler::class, [
            null,
            true,
            [
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_QUIET => 500,
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_NORMAL => 400,
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE => 300,
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE => 250,
                \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_DEBUG => 200,
            ],
            [],
            false,
        ], [
            ['pushProcessor', $psr3],
        ]];

        yield 'firephp' => ['firephp', ['firephp' => [
            'type' => 'firephp',
            'level' => 'WARNING',
        ]], \Symfony\Bridge\Monolog\Handler\FirePHPHandler::class, ['WARNING', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'chromephp' => ['chromephp', ['chromephp' => [
            'type' => 'chromephp',
            'level' => 'WARNING',
        ]], \Symfony\Bridge\Monolog\Handler\ChromePhpHandler::class, ['WARNING', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'slack' => ['slack', ['slack' => [
            'type' => 'slack',
            'token' => 'slack-token',
            'channel' => '#logs',
            'exclude_fields' => ['field1'],
        ]], \Monolog\Handler\SlackHandler::class, ['slack-token', '#logs', 'Monolog', true, null, 'DEBUG', true, false, false, ['field1']], [
            ['pushProcessor', $psr3],
        ]];

        yield 'slackwebhook' => ['slackwebhook', ['slackwebhook' => [
            'type' => 'slackwebhook',
            'webhook_url' => 'https://hooks.slack.com/services/...',
            'channel' => '#logs',
            'exclude_fields' => ['field1'],
        ]], \Monolog\Handler\SlackWebhookHandler::class, ['https://hooks.slack.com/services/...', '#logs', 'Monolog', true, null, false, false, 'DEBUG', true, ['field1']], [
            ['pushProcessor', $psr3],
        ]];

        yield 'gelf' => ['gelf', ['gelf' => [
            'type' => 'gelf',
            'publisher' => ['id' => 'gelf.publisher'],
        ]], \Monolog\Handler\GelfHandler::class, [new Reference('gelf.publisher'), 'DEBUG', true], [
            ['pushProcessor', $psr3],
        ]];

        yield 'fingers_crossed' => ['fingers_crossed', [
            'fingers_crossed' => [
                'type' => 'fingers_crossed',
                'action_level' => 'WARNING',
                'handler' => 'nested',
                'buffer_size' => 30,
                'stop_buffering' => true,
                'passthru_level' => null,
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\FingersCrossedHandler::class, [
            new Reference('monolog.handler.nested'),
            new Definition(\Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy::class, ['WARNING']),
            30,
            true,
            true,
            null,
        ]];

        yield 'filter' => ['filter', [
            'filter' => [
                'type' => 'filter',
                'handler' => 'nested',
                'accepted_levels' => ['WARNING', 'ERROR'],
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\FilterHandler::class, [
            new Reference('monolog.handler.nested'),
            ['WARNING', 'ERROR'],
            'EMERGENCY',
            true,
        ]];

        yield 'buffer' => ['buffer', [
            'buffer' => [
                'type' => 'buffer',
                'handler' => 'nested',
                'buffer_size' => 5,
                'flush_on_overflow' => false,
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\BufferHandler::class, [
            new Reference('monolog.handler.nested'),
            5,
            'DEBUG',
            true,
            false,
        ]];

        yield 'deduplication' => ['deduplication', [
            'deduplication' => [
                'type' => 'deduplication',
                'handler' => 'nested',
                'time' => 60,
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\DeduplicationHandler::class, [
            new Reference('monolog.handler.nested'),
            '%kernel.cache_dir%/monolog_dedup_'.sha1('monolog.handler.deduplication'),
            \Monolog\Level::Error->value,
            60,
            true,
        ]];

        yield 'sampling' => ['sampling', [
            'sampling' => [
                'type' => 'sampling',
                'handler' => 'nested',
                'factor' => 10,
            ],
            'nested' => $nested('nested'),
        ], \Monolog\Handler\SamplingHandler::class, [
            new Reference('monolog.handler.nested'),
            10,
        ]];

        yield 'group' => ['group', [
            'group' => ['type' => 'group', 'members' => ['a', 'b']],
            'a' => $nested('a'),
            'b' => $nested('b'),
        ], \Monolog\Handler\GroupHandler::class, [
            [new Reference('monolog.handler.a'), new Reference('monolog.handler.b')],
            true,
        ]];

        yield 'whatfailuregroup' => ['whatfailuregroup', [
            'whatfailuregroup' => ['type' => 'whatfailuregroup', 'members' => ['a', 'b']],
            'a' => $nested('a'),
            'b' => $nested('b'),
        ], \Monolog\Handler\WhatFailureGroupHandler::class, [
            [new Reference('monolog.handler.a'), new Reference('monolog.handler.b')],
            true,
        ]];

        yield 'fallbackgroup' => ['fallbackgroup', [
            'fallbackgroup' => ['type' => 'fallbackgroup', 'members' => ['a', 'b']],
            'a' => $nested('a'),
            'b' => $nested('b'),
        ], \Monolog\Handler\FallbackGroupHandler::class, [
            [new Reference('monolog.handler.a'), new Reference('monolog.handler.b')],
            true,
        ]];

        yield 'native_mailer' => ['native_mailer', ['native_mailer' => [
            'type' => 'native_mailer',
            'from_email' => 'f@example.com',
            'to_email' => ['t@example.com'],
            'subject' => 'Subject',
            'headers' => ['Foo: bar'],
            'parameters' => ['--foo'],
        ]], \Monolog\Handler\NativeMailerHandler::class, [['t@example.com'], 'Subject', 'f@example.com', 'DEBUG', true], [
            ['pushProcessor', $psr3],
            ['addHeader', [['Foo: bar']]],
            ['addParameter', [['--foo']]],
        ]];

        $symfonyMailerPrototype = (new Definition(\Symfony\Component\Mime\Email::class))
            ->setPublic(false)
            ->addMethodCall('from', ['f@example.com'])
            ->addMethodCall('to', ['t@example.com'])
            ->addMethodCall('subject', ['Subject']);

        yield 'symfony_mailer' => ['symfony_mailer', ['symfony_mailer' => [
            'type' => 'symfony_mailer',
            'from_email' => 'f@example.com',
            'to_email' => ['t@example.com'],
            'subject' => 'Subject',
        ]], \Symfony\Bridge\Monolog\Handler\MailerHandler::class, [
            new Reference('mailer.mailer'),
            $symfonyMailerPrototype,
            'DEBUG',
            true,
        ], [
            ['pushProcessor', $psr3],
        ]];
    }

    private function handlerServiceDependencies(): array
    {
        return [
            'gelf.publisher' => new Definition(\stdClass::class),
            'my.exchange' => new Definition(\stdClass::class),
            'mailer.mailer' => new Definition(\stdClass::class),
        ];
    }

    public function testLoadWithTimezone()
    {
        $container = $this->getContainer([['timezone' => 'Europe/Paris', 'handlers' => ['main' => ['type' => 'stream']]]]);

        // the timezone is set on the logger prototype, so every logger (the
        // "app" logger and the channel loggers) inherits it through its constructor
        $prototype = $container->getDefinition('monolog.logger_prototype');
        $this->assertEquals(new Definition(\DateTimeZone::class, ['Europe/Paris']), $prototype->getArgument(3));
    }

    public function testLoadWithoutTimezoneDoesNotSetTimezone()
    {
        $container = $this->getContainer([['handlers' => ['main' => ['type' => 'stream']]]]);

        $prototype = $container->getDefinition('monolog.logger_prototype');
        $this->assertArrayNotHasKey(3, $prototype->getArguments());
    }

    public function testLoadWithNestedHandler()
    {
        $container = $this->getContainer([['handlers' => [
            'custom' => ['type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR', 'file_permission' => '0666'],
            'nested' => ['type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR', 'file_permission' => '0666', 'nested' => true],
        ]]]);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));

        $logger = $container->getDefinition('monolog.logger');
        // Nested handler must not be pushed to logger
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.custom')]);

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\StreamHandler::class);
        $this->assertDICConstructorArguments($handler, ['/tmp/symfony.log', 'ERROR', false, 0666, false]);
    }

    public function testAllHandlersAreTaggedForClosing()
    {
        $container = $this->getContainer([['handlers' => [
            'custom' => ['type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR', 'file_permission' => '0666'],
            'nested' => ['type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR', 'file_permission' => '0666', 'nested' => true],
        ]]]);
        $taggedHandlers = $container->findTaggedServiceIds('monolog.handler');

        // Both top-level and nested handlers are tagged, as nested handlers hold resources
        // that must be released on shutdown even though their wrapper handles the reset.
        $this->assertCount(2, $taggedHandlers);
        $this->assertArrayHasKey('monolog.handler.custom', $taggedHandlers);
        $this->assertArrayHasKey('monolog.handler.nested', $taggedHandlers);
    }

    public function testHandlerLifecycleManagerReceivesWeakReferencesToAllHandlers()
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->addCompilerPass(new LoggerChannelPass());
        $container->addCompilerPass(new AddHandlersToManagerPass());
        (new MonologExtension())->load([['handlers' => [
            'main' => ['type' => 'stream', 'path' => '/tmp/symfony.log'],
            'other' => ['type' => 'stream', 'path' => '/tmp/other.log'],
        ]]], $container);
        $container->compile();

        $this->assertTrue($container->hasDefinition('monolog.handler_lifecycle_manager'));

        $arguments = $container->getDefinition('monolog.handler_lifecycle_manager')->getArguments();
        $this->assertCount(1, $arguments);

        $iterator = $arguments[0];
        $this->assertInstanceOf(IteratorArgument::class, $iterator);

        $references = $iterator->getValues();
        $this->assertCount(2, $references);

        // Weak references so only handlers already instantiated are closed at shutdown.
        foreach ($references as $reference) {
            $this->assertInstanceOf(Reference::class, $reference);
            $this->assertSame(ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE, $reference->getInvalidBehavior());
        }
    }

    public function testLoadWithGroupHandlerAndDisabledMember()
    {
        $container = $this->getContainer([['handlers' => [
            'main' => ['type' => 'group', 'members' => ['enabled_member', 'disabled_member']],
            'enabled_member' => ['type' => 'stream', 'path' => '/tmp/symfony.log'],
            'disabled_member' => ['type' => 'stream', 'path' => '/tmp/symfony.log', 'enabled' => false],
        ]]]);

        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.enabled_member'));
        // a disabled handler is not registered as a service...
        $this->assertFalse($container->hasDefinition('monolog.handler.disabled_member'));

        // ...and is therefore skipped from the group it belongs to, instead of
        // leaving a reference to a non-existent service (see issue #561)
        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, 'Monolog\Handler\GroupHandler');
        $this->assertDICConstructorArguments($handler, [[new Reference('monolog.handler.enabled_member')], true]);
    }

    public function testLoadWithServiceHandler()
    {
        $container = $this->getContainer(
            [['handlers' => ['custom' => ['type' => 'service', 'id' => 'some.service.id']]]],
            ['some.service.id' => new Definition(\stdClass::class, ['foo', false])]
        );

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasAlias('monolog.handler.custom'));

        $logger = $container->getDefinition('monolog.logger');
        // Custom service handler must be pushed to logger
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.custom')]);

        $handler = $container->findDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, \stdClass::class);
        $this->assertDICConstructorArguments($handler, ['foo', false]);
    }

    public function testLoadWithNestedServiceHandler()
    {
        $container = $this->getContainer(
            [['handlers' => ['custom' => ['type' => 'service', 'id' => 'some.service.id', 'nested' => true]]]],
            ['some.service.id' => new Definition(\stdClass::class, ['foo', false])]
        );

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasAlias('monolog.handler.custom'));

        $logger = $container->getDefinition('monolog.logger');
        // Nested service handler must not be pushed to logger
        $this->assertCount(1, $logger->getMethodCalls());
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);

        $handler = $container->findDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, \stdClass::class);
        $this->assertDICConstructorArguments($handler, ['foo', false]);
    }

    public function testExceptionWhenInvalidHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $this->expectException(\InvalidArgumentException::class);

        $loader->load([['handlers' => ['main' => ['type' => 'invalid_handler']]]], $container);
    }

    public function testExceptionWhenUsingFingerscrossedWithoutHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load([['handlers' => ['main' => ['type' => 'fingers_crossed']]]], $container);
    }

    public function testExceptionWhenUsingFilterWithoutHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load([['handlers' => ['main' => ['type' => 'filter']]]], $container);
    }

    public function testExceptionWhenUsingBufferWithoutHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load([['handlers' => ['main' => ['type' => 'buffer']]]], $container);
    }

    public function testExceptionWhenUsingGelfWithoutPublisher()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load([['handlers' => ['gelf' => ['type' => 'gelf']]]], $container);
    }

    public function testExceptionWhenUsingGelfWithoutPublisherHostname()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load([['handlers' => ['gelf' => ['type' => 'gelf', 'publisher' => []]]]], $container);
    }

    public function testExceptionWhenUsingServiceWithoutId()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load([['handlers' => ['main' => ['type' => 'service']]]], $container);
    }

    public function testExceptionWhenUsingDebugName()
    {
        // logger
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load([['handlers' => ['debug' => ['type' => 'stream']]]], $container);
    }

    public function testSyslogHandlerWithLogopts()
    {
        $container = $this->getContainer([['handlers' => ['main' => ['type' => 'syslog', 'logopts' => \LOG_CONS]]]]);

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.main')]);

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\SyslogHandler::class);
        $this->assertDICConstructorArguments($handler, ['php', 'user', 'DEBUG', true, \LOG_CONS]);
    }

    public function testSyslogUdpHandler()
    {
        $container = $this->getContainer([
            ['handlers' => [
                'syslogudp' => [
                    'type' => 'syslogudp',
                    'host' => '127.0.0.1',
                    'port' => 514,
                    'facility' => 'USER',
                    'level' => 'ERROR',
                    'ident' => null,
                    'rfc' => SyslogUdpHandler::RFC5424,
                ],
            ]],
        ]);

        $handler = $container->getDefinition('monolog.handler.syslogudp');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\SyslogUdpHandler::class);
        $this->assertDICConstructorArguments($handler, ['127.0.0.1', 514, 'USER', 'ERROR', true, 'php', SyslogUdpHandler::RFC5424]);
    }

    public function testRollbarHandlerCreatesNotifier()
    {
        $container = $this->getContainer([['handlers' => ['main' => ['type' => 'rollbar', 'token' => 'MY_TOKEN']]]]);

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.main')]);

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, RollbarHandler::class);
        $this->assertDICConstructorArguments($handler, [new Reference('monolog.rollbar.notifier.1c8e6a67728dff6a209f828427128dd8b3c2b746'), 'DEBUG', true]);
    }

    public function testRollbarHandlerReusesNotifier()
    {
        $container = $this->getContainer(
            [['handlers' => ['main' => ['type' => 'rollbar', 'id' => 'my_rollbar_id']]]],
            ['my_rollbar_id' => new Definition(RollbarHandler::class)]
        );

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.main')]);

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, RollbarHandler::class);
        $this->assertDICConstructorArguments($handler, [new Reference('my_rollbar_id'), 'DEBUG', true]);
    }

    public function testSocketHandler()
    {
        try {
            $this->getContainer([['handlers' => ['socket' => ['type' => 'socket']]]]);
            $this->fail();
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString('connection_string', $e->getMessage());
        }

        $container = $this->getContainer([['handlers' => ['socket' => [
            'type' => 'socket', 'timeout' => 1, 'persistent' => true,
            'connection_string' => 'localhost:50505', 'connection_timeout' => '0.6',
        ]]]]);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.socket'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.socket')]);

        $handler = $container->getDefinition('monolog.handler.socket');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\SocketHandler::class);
        $this->assertDICConstructorArguments($handler, ['localhost:50505', 'DEBUG', true]);
        $this->assertDICDefinitionMethodCallAt(0, $handler, 'pushProcessor', [new Reference('monolog.processor.psr_log_message')]);
        $this->assertDICDefinitionMethodCallAt(1, $handler, 'setTimeout', ['1']);
        $this->assertDICDefinitionMethodCallAt(2, $handler, 'setConnectionTimeout', ['0.6']);
        $this->assertDICDefinitionMethodCallAt(3, $handler, 'setPersistent', [true]);
    }

    public function testLogglyHandler()
    {
        $token = '026308d8-2b63-4225-8fe9-e01294b6e472';
        try {
            $this->getContainer([['handlers' => ['loggly' => ['type' => 'loggly']]]]);
            $this->fail();
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString('token', $e->getMessage());
        }

        try {
            $this->getContainer([['handlers' => ['loggly' => [
                'type' => 'loggly', 'token' => $token, 'tags' => 'x, 1zone ,www.loggly.com,-us,apache$',
            ]]]]);
            $this->fail();
        } catch (InvalidConfigurationException $e) {
            $this->assertSame('The following Loggly tags are invalid: "-us", "apache$".', $e->getMessage());
        }

        $container = $this->getContainer([['handlers' => ['loggly' => [
            'type' => 'loggly', 'token' => $token,
        ]]]]);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.loggly'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.loggly')]);
        $handler = $container->getDefinition('monolog.handler.loggly');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\LogglyHandler::class);
        $this->assertDICConstructorArguments($handler, [$token, 'DEBUG', true]);
        $this->assertDICDefinitionMethodCallAt(0, $handler, 'pushProcessor', [new Reference('monolog.processor.psr_log_message')]);

        $container = $this->getContainer([['handlers' => ['loggly' => [
            'type' => 'loggly', 'token' => $token, 'tags' => [' ', 'foo', '', 'bar'],
        ]]]]);
        $handler = $container->getDefinition('monolog.handler.loggly');
        $this->assertDICDefinitionMethodCallAt(0, $handler, 'pushProcessor', [new Reference('monolog.processor.psr_log_message')]);
        $this->assertDICDefinitionMethodCallAt(1, $handler, 'setTag', ['foo,bar']);
    }

    public function testFingersCrossedHandlerWhenExcludedHttpCodesAreSpecified()
    {
        $activation = new Definition(ErrorLevelActivationStrategy::class, ['WARNING']);

        $container = $this->getContainer([['handlers' => [
            'main' => [
                'type' => 'fingers_crossed',
                'handler' => 'nested',
                'excluded_http_codes' => [403, 404, [405 => ['^/foo', '^/bar']]],
            ],
            'nested' => ['type' => 'stream', 'path' => '/tmp/symfony.log'],
        ]]], ['request_stack' => new Definition(RequestStack::class)]);

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main.http_code_strategy'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'useMicrosecondTimestamps', ['%monolog.use_microseconds%']);
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', [new Reference('monolog.handler.main')]);

        $strategy = $container->getDefinition('monolog.handler.main.http_code_strategy');
        $this->assertDICDefinitionClass($strategy, \Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy::class);
        $this->assertDICConstructorArguments($strategy, [
            new Reference('request_stack'),
            [
                ['code' => 403, 'urls' => []],
                ['code' => 404, 'urls' => []],
                ['code' => 405, 'urls' => ['^/foo', '^/bar']],
            ],
            $activation,
        ]);

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, \Monolog\Handler\FingersCrossedHandler::class);
        $this->assertDICConstructorArguments($handler, [new Reference('monolog.handler.nested'), new Reference('monolog.handler.main.http_code_strategy'), 0, true, true, null]);
    }

    #[DataProvider('provideLoglevelParameterConfig')]
    public function testLogLevelfromParameter(array $parameters, array $config, string $expectedClass, array $expectedArgs)
    {
        $container = new ContainerBuilder();
        foreach ($parameters as $name => $value) {
            $container->setParameter($name, $value);
        }
        $loader = new MonologExtension();
        $config = [['handlers' => ['main' => $config]]];
        $loader->load($config, $container);

        $definition = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($definition, $expectedClass);
        $this->assertDICConstructorArguments($definition, $expectedArgs);
    }

    public static function provideLoglevelParameterConfig(): array
    {
        return [
            'browser console with parameter level' => [
                ['%log_level%' => 'info'],
                ['type' => 'browser_console', 'level' => '%log_level%'],
                \Monolog\Handler\BrowserConsoleHandler::class,
                [
                    '%log_level%',
                    true,
                ],
            ],
            'browser console with envvar level' => [
                ['%env(LOG_LEVEL)%' => 'info'],
                ['type' => 'browser_console', 'level' => '%env(LOG_LEVEL)%'],
                \Monolog\Handler\BrowserConsoleHandler::class,
                [
                    '%env(LOG_LEVEL)%',
                    true,
                ],
            ],
            'stream with envvar level null or "~" (in yaml config)' => [
                ['%env(LOG_LEVEL)%' => null],
                ['type' => 'stream', 'level' => '%env(LOG_LEVEL)%'],
                \Monolog\Handler\StreamHandler::class,
                [
                    '%kernel.logs_dir%/%kernel.environment%.log',
                    '%env(LOG_LEVEL)%',
                    true,
                    null,
                    false,
                ],
            ],
            'stream with envvar level' => [
                ['%env(LOG_LEVEL)%' => '400'],
                ['type' => 'stream', 'level' => '%env(LOG_LEVEL)%'],
                \Monolog\Handler\StreamHandler::class,
                [
                    '%kernel.logs_dir%/%kernel.environment%.log',
                    '%env(LOG_LEVEL)%',
                    true,
                    null,
                    false,
                ],
            ],
            'stream with envvar and fallback parameter' => [
                ['%env(LOG_LEVEL)%' => '500', '%log_level%' => '%env(LOG_LEVEL)%'],
                ['type' => 'stream', 'level' => '%log_level%'],
                \Monolog\Handler\StreamHandler::class,
                [
                    '%kernel.logs_dir%/%kernel.environment%.log',
                    '%log_level%',
                    true,
                    null,
                    false,
                ],
            ],
        ];
    }

    public function testProcessorAutoConfiguration()
    {
        $service = new Definition(UidProcessor::class);
        $service->setAutoconfigured(true);
        $container = $this->getContainer([], ['processor.uid' => $service]);
        $this->assertTrue($container->hasDefinition('processor.uid'));
        $processor = $container->getDefinition('processor.uid');
        $tags = $processor->getTags();
        $this->assertArrayHasKey('kernel.reset', $tags);
        $this->assertIsArray($tags['kernel.reset']);
        $this->assertCount(1, $tags['kernel.reset']);
        $this->assertIsArray($tags['kernel.reset'][0]);
        $this->assertArrayHasKey('method', $tags['kernel.reset'][0]);
        $this->assertEquals('reset', $tags['kernel.reset'][0]['method']);
    }

    public function testAsMonologProcessorAutoconfigurationRedeclareMethod()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('AsMonologProcessor attribute cannot declare a method on "Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures\AsMonologProcessor\RedeclareMethodProcessor::__invoke()".');

        $this->getContainer([], [
            RedeclareMethodProcessor::class => (new Definition(RedeclareMethodProcessor::class))->setAutoconfigured(true),
        ]);
    }

    public function testAsMonologProcessorAutoconfigurationWithPriority()
    {
        $container = $this->getContainer([], [
            FooProcessorWithPriority::class => (new Definition(FooProcessorWithPriority::class))->setAutoconfigured(true),
        ]);

        $this->assertSame([
            [
                'channel' => null,
                'handler' => 'foo_handler',
                'method' => null,
                'priority' => null,
            ],
            [
                'channel' => 'ccc_channel',
                'handler' => null,
                'method' => '__invoke',
                'priority' => 10,
            ],
        ], $container->getDefinition(FooProcessorWithPriority::class)->getTag('monolog.processor'));
    }

    public function testWithLoggerChannelAutoconfiguration()
    {
        $container = $this->getContainer([], [
            ServiceWithChannel::class => (new Definition(ServiceWithChannel::class))->setAutoconfigured(true),
        ]);

        $this->assertSame([
            [
                'channel' => 'fixture',
            ],
        ], $container->getDefinition(ServiceWithChannel::class)->getTag('monolog.logger'));
    }

    public function testWithLoggerChannelAutoconfigurationOnArgument()
    {
        $container = $this->getContainer([], [
            ServiceWithChannelOnArgument::class => (new Definition(ServiceWithChannelOnArgument::class))->setAutoconfigured(true),
        ]);

        $this->assertSame([
            [
                'channel' => 'fixture',
                'argument' => 'logger',
            ],
            [
                'channel' => 'fixture_bis',
                'argument' => 'otherLogger',
            ],
        ], $container->getDefinition(ServiceWithChannelOnArgument::class)->getTag('monolog.logger'));
    }

    public function testElasticsearchAndElasticaHandlers()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('elasticsearch.client', new Definition(\Elasticsearch\Client::class));
        $container->setDefinition('elastica.client', new Definition(\Elastica\Client::class));

        $config = [[
            'handlers' => [
                'es_handler' => [
                    'type' => 'elastic_search',
                    'elasticsearch' => [
                        'hosts' => ['es:9200'],
                    ],
                    'index' => 'my-index',
                    'document_type' => 'my-type',
                ],
                'elastica_handler' => [
                    'type' => 'elastica',
                    'elasticsearch' => [
                        'hosts' => ['es:9200'],
                    ],
                    'index' => 'my-index',
                    'document_type' => 'my-type',
                ],
            ],
        ]];

        $extension = new MonologExtension();
        $extension->load($config, $container);

        $this->assertTrue($container->hasDefinition('monolog.handler.es_handler'));
        $this->assertTrue($container->hasDefinition('monolog.handler.elastica_handler'));

        // Elasticsearch handler should receive the elasticsearch.client as first argument
        $esHandler = $container->getDefinition('monolog.handler.es_handler');
        $this->assertSame(ElasticsearchHandler::class, $esHandler->getClass());
        $esClient = $esHandler->getArgument(0);
        $this->assertInstanceOf(Definition::class, $esClient);
        $this->assertStringEndsWith(\Elasticsearch\Client::class, $esClient->getClass());
        $this->assertSame(['hosts' => ['es:9200']], $esClient->getArgument(0));

        // Elastica handler should receive the elastica.client as first argument
        $elasticaHandler = $container->getDefinition('monolog.handler.elastica_handler');
        $this->assertSame(ElasticaHandler::class, $elasticaHandler->getClass());
        $elasticaClient = $elasticaHandler->getArgument(0);
        $this->assertInstanceOf(Definition::class, $elasticaClient);
        $this->assertSame(\Elastica\Client::class, $elasticaClient->getClass());
        $this->assertSame(['hosts' => ['es:9200'], 'transport' => 'Http'], $elasticaClient->getArgument(0));
    }

    public function testMongoDB()
    {
        if (!class_exists('MongoDB\Client')) {
            $this->markTestSkipped('mongodb/mongodb is not installed.');
        }

        $container = new ContainerBuilder();
        $container->setDefinition('mongodb.client', new Definition('MongoDB\Client'));

        $config = [[
            'handlers' => [
                'mongodb_with_id' => [
                    'type' => 'mongodb',
                    'mongodb' => ['id' => 'mongodb.client'],
                ],
                'mongodb_with_string_id' => [
                    'type' => 'mongodb',
                    'mongodb' => 'mongodb.client',
                ],
                'mongodb_with_uri' => [
                    'type' => 'mongodb',
                    'mongodb' => [
                        'uri' => 'mongodb://localhost:27018',
                        'username' => 'username',
                        'password' => 'password',
                        'database' => 'db',
                        'collection' => 'coll',
                    ],
                ],
                'mongodb_with_uri_and_default_args' => [
                    'type' => 'mongodb',
                    'mongodb' => [
                        'uri' => 'mongodb://localhost:27018',
                    ],
                ],
                'mongodb_without_explicit_type' => [
                    'mongodb' => [
                        'uri' => 'mongodb://localhost:27018',
                    ],
                ],
            ],
        ]];

        $extension = new MonologExtension();
        $extension->load($config, $container);

        $this->assertTrue($container->hasDefinition('monolog.handler.mongodb_with_id'));
        $this->assertTrue($container->hasDefinition('monolog.handler.mongodb_with_string_id'));
        $this->assertTrue($container->hasDefinition('monolog.handler.mongodb_with_uri'));
        $this->assertTrue($container->hasDefinition('monolog.handler.mongodb_with_uri_and_default_args'));

        // A MongoDBFormatter will be applied to each handler by default
        $formatter = new Definition('Monolog\Formatter\MongoDBFormatter');

        // MongoDB handler should receive the mongodb.client as first argument
        $handler = $container->getDefinition('monolog.handler.mongodb_with_id');
        $this->assertDICDefinitionClass($handler, MongoDBHandler::class);
        $this->assertDICConstructorArguments($handler, [new Reference('mongodb.client'), 'monolog', 'logs', 'DEBUG', true]);
        $this->assertDICDefinitionMethodCallAt(1, $handler, 'setFormatter', [$formatter]);

        // MongoDB handler should receive the mongodb.client as first argument
        $handler = $container->getDefinition('monolog.handler.mongodb_with_string_id');
        $this->assertDICDefinitionClass($handler, MongoDBHandler::class);
        $this->assertDICConstructorArguments($handler, [new Reference('mongodb.client'), 'monolog', 'logs', 'DEBUG', true]);
        $this->assertDICDefinitionMethodCallAt(1, $handler, 'setFormatter', [$formatter]);

        // MongoDB handler with arguments
        $handler = $container->getDefinition('monolog.handler.mongodb_with_uri');
        $this->assertDICDefinitionClass($handler, MongoDBHandler::class);
        $client = $handler->getArgument(0);
        $this->assertDICDefinitionClass($client, 'MongoDB\Client');
        $this->assertDICConstructorArguments($client, ['mongodb://localhost:27018', ['appname' => 'monolog-bundle', 'username' => 'username', 'password' => 'password']]);
        $this->assertDICConstructorArguments($handler, [$client, 'db', 'coll', 'DEBUG', true]);
        $this->assertDICDefinitionMethodCallAt(1, $handler, 'setFormatter', [$formatter]);

        // MongoDB handler with host and default arguments
        $handler = $container->getDefinition('monolog.handler.mongodb_with_uri_and_default_args');
        $this->assertDICDefinitionClass($handler, MongoDBHandler::class);
        $client = $handler->getArgument(0);
        $this->assertDICDefinitionClass($client, 'MongoDB\Client');
        $this->assertDICConstructorArguments($client, ['mongodb://localhost:27018', ['appname' => 'monolog-bundle']]);
        $this->assertDICConstructorArguments($handler, [$client, 'monolog', 'logs', 'DEBUG', true]);
        $this->assertDICDefinitionMethodCallAt(1, $handler, 'setFormatter', [$formatter]);

        // MongoDB handler without an explicit type
        $handler = $container->getDefinition('monolog.handler.mongodb_without_explicit_type');
        $this->assertDICDefinitionClass($handler, MongoDBHandler::class);
        $client = $handler->getArgument(0);
        $this->assertDICDefinitionClass($client, 'MongoDB\Client');
        $this->assertDICConstructorArguments($client, ['mongodb://localhost:27018', ['appname' => 'monolog-bundle']]);
    }

    public function testBasePathOption()
    {
        $container = $this->getContainer([['handlers' => [
            'main' => [
                'type' => 'stream',
                'path' => '/tmp/symfony.log',
                'base_path' => '/var/www/project',
            ],
        ]]]);

        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $handler = $container->getDefinition('monolog.handler.main');
        $configuratorRef = $handler->getConfigurator();
        $this->assertIsArray($configuratorRef);
        [$configurator, $method] = $configuratorRef;
        $this->assertInstanceOf(Definition::class, $configurator);
        $this->assertSame('__invoke', $method);
        $this->assertDICDefinitionClass($configurator, FormatterConfigurator::class);
        $this->assertDICConstructorArguments($configurator, [false, '/var/www/project']);
    }

    public function testBasePathWithIncludeStacktraces()
    {
        $container = $this->getContainer([['handlers' => [
            'main' => [
                'type' => 'stream',
                'path' => '/tmp/symfony.log',
                'base_path' => '/var/www/project',
                'include_stacktraces' => true,
            ],
        ]]]);

        $handler = $container->getDefinition('monolog.handler.main');
        $configuratorRef = $handler->getConfigurator();
        $this->assertIsArray($configuratorRef);
        [$configurator, $method] = $configuratorRef;
        $this->assertInstanceOf(Definition::class, $configurator);
        $this->assertSame('__invoke', $method);
        $this->assertDICDefinitionClass($configurator, FormatterConfigurator::class);
        $this->assertDICConstructorArguments($configurator, [true, '/var/www/project']);
    }

    public function testIncludeStacktracesWithFormatterConfigurator()
    {
        $container = $this->getContainer([['handlers' => [
            'main' => [
                'type' => 'stream',
                'path' => '/tmp/symfony.log',
                'include_stacktraces' => true,
            ],
        ]]]);

        $handler = $container->getDefinition('monolog.handler.main');
        $configuratorRef = $handler->getConfigurator();
        $this->assertIsArray($configuratorRef);
        [$configurator, $method] = $configuratorRef;
        $this->assertInstanceOf(Definition::class, $configurator);
        $this->assertSame('__invoke', $method);
        $this->assertDICDefinitionClass($configurator, FormatterConfigurator::class);
        $this->assertDICConstructorArguments($configurator, [true, null]);
    }

    private function getContainer(array $config = [], array $thirdPartyDefinitions = []): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        foreach ($thirdPartyDefinitions as $id => $definition) {
            $container->setDefinition($id, $definition);
        }

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->addCompilerPass(new LoggerChannelPass());

        $loader = new MonologExtension();
        $loader->load($config, $container);
        $container->compile();

        return $container;
    }
}
