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
use Monolog\Processor\UidProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\LoggerChannelPass;
use Symfony\Bundle\MonologBundle\DependencyInjection\FormatterConfigurator;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures\AsMonologProcessor\FooProcessorWithPriority;
use Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures\AsMonologProcessor\RedeclareMethodProcessor;
use Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures\ServiceWithChannel;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
        $this->assertDICConstructorArguments($handler, [false, 'user', 'DEBUG', true, \LOG_CONS]);
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
