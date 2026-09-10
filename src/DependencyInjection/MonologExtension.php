<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\Attribute\WithMonologChannel;
use Monolog\Handler;
use Monolog\Handler\HandlerInterface;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\ResettableInterface;
use Symfony\Bridge\Monolog\Processor\TokenProcessor;
use Symfony\Bridge\Monolog\Handler\ChromePhpHandler;
use Symfony\Bridge\Monolog\Handler\FirePHPHandler;
use Symfony\Bundle\MonologBundle\DependencyInjection\Handler as HandlerExtension;
use Symfony\Bundle\MonologBundle\DependencyInjection\Handler\HandlerContext;
use Symfony\Bundle\MonologBundle\DependencyInjection\Handler\HandlerExtensionInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * MonologExtension is an extension for the Monolog library.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 */
final class MonologExtension extends Extension
{
    /** @var list<string> */
    private array $nestedHandlers = [];

    /** @var array<string, true> */
    private array $disabledHandlers = [];

    /**
     * @var array<string, HandlerExtensionInterface>
     */
    private array $handlerExtensions = [];

    public function __construct()
    {
        $this->addHandlerExtension(new HandlerExtension\DefaultHandlerExtension('null', Handler\NullHandler::class));
        $this->addHandlerExtension(new HandlerExtension\DefaultHandlerExtension('noop', Handler\NoopHandler::class));
        $this->addHandlerExtension(new HandlerExtension\DefaultHandlerExtension('debug', DebugHandler::class));
        $this->addHandlerExtension(new HandlerExtension\DefaultHandlerExtension('test', Handler\TestHandler::class));
        $this->addHandlerExtension(new HandlerExtension\DefaultHandlerExtension('browser_console', Handler\BrowserConsoleHandler::class));
        $this->addHandlerExtension(new HandlerExtension\KernelResponseHandlerExtension('firephp', FirePHPHandler::class));
        $this->addHandlerExtension(new HandlerExtension\KernelResponseHandlerExtension('chromephp', ChromePhpHandler::class));
        $this->addHandlerExtension(new HandlerExtension\MongoDBHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\StreamHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\RotatingFileHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\SocketHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\SyslogHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\SyslogUdpHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\CubeHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\ErrorLogHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\ServerLogHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\AmqpHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\LogEntriesHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\LogglyHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\InsightOpsHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\FlowdockHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\PushoverHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\TelegramBotHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\RollbarHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\NewRelicHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\ConsoleHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\SlackHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\SlackWebhookHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\NativeMailerHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\SymfonyMailerHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\GelfHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\FingersCrossedHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\FilterHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\BufferHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\DeduplicationHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\SamplingHandlerExtension());
        $this->addHandlerExtension(new HandlerExtension\GroupHandlerExtension('group', Handler\GroupHandler::class));
        $this->addHandlerExtension(new HandlerExtension\GroupHandlerExtension('whatfailuregroup', Handler\WhatFailureGroupHandler::class));
        $this->addHandlerExtension(new HandlerExtension\GroupHandlerExtension('fallbackgroup', Handler\FallbackGroupHandler::class));
    }

    public function addHandlerExtension(HandlerExtensionInterface $extension): void
    {
        $this->handlerExtensions[$extension->getName()] = $extension;
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new Configuration($this->handlerExtensions);
    }

    /**
     * Loads the Monolog configuration.
     *
     * @param array            $configs   An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (isset($config['handlers'])) {
            $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
            $loader->load('monolog.php');

            $container->setParameter('monolog.use_microseconds', $config['use_microseconds']);

            if (null !== $config['timezone']) {
                $prototype = $container->getDefinition('monolog.logger_prototype');
                $prototype->setArgument(1, []);
                $prototype->setArgument(2, []);
                $prototype->setArgument(3, new Definition(\DateTimeZone::class, [$config['timezone']]));
            }

            $handlers = [];

            // Collect disabled handlers first so that group members referencing
            // them can be skipped, regardless of the order they are declared in.
            foreach ($config['handlers'] as $name => $handler) {
                if (!$handler['enabled']) {
                    $this->disabledHandlers[$name] = true;
                }
            }

            foreach ($config['handlers'] as $name => $handler) {
                if (!$handler['enabled']) {
                    continue;
                }
                $handlers[$handler['priority']][] = [
                    'id' => $this->buildHandler($container, $name, $handler),
                    'channels' => empty($handler['channels']) ? null : $handler['channels'],
                ];
            }

            ksort($handlers);
            $sortedHandlers = [];
            foreach ($handlers as $priorityHandlers) {
                foreach (array_reverse($priorityHandlers) as $handler) {
                    $sortedHandlers[] = $handler;
                }
            }

            $handlersToChannels = [];
            foreach ($sortedHandlers as $handler) {
                if (!\in_array($handler['id'], $this->nestedHandlers)) {
                    $handlersToChannels[$handler['id']] = $handler['channels'];
                }
            }
            $container->setParameter('monolog.handlers_to_channels', $handlersToChannels);
        }

        $container->setParameter('monolog.additional_channels', $config['channels'] ?? []);

        $container->registerForAutoconfiguration(ProcessorInterface::class)
            ->addTag('monolog.processor');
        $container->registerForAutoconfiguration(ResettableInterface::class)
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->registerForAutoconfiguration(TokenProcessor::class)
            ->addTag('monolog.processor');

        if (interface_exists(HttpClientInterface::class)) {
            $handlerAutoconfiguration = $container->registerForAutoconfiguration(HandlerInterface::class);
            $handlerAutoconfiguration->setBindings($handlerAutoconfiguration->getBindings() + [
                HttpClientInterface::class => new BoundArgument(new Reference('monolog.http_client'), false),
            ]);
        }

        $container->registerAttributeForAutoconfiguration(AsMonologProcessor::class, static function (ChildDefinition $definition, AsMonologProcessor $attribute, \Reflector $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new \LogicException(\sprintf('AsMonologProcessor attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }

                $tagAttributes['method'] = $reflector->getName();
            }

            $definition->addTag('monolog.processor', $tagAttributes);
        });
        $container->registerAttributeForAutoconfiguration(WithMonologChannel::class, static function (ChildDefinition $definition, WithMonologChannel $attribute): void {
            $definition->addTag('monolog.logger', ['channel' => $attribute->channel]);
        });
        $container->registerAttributeForAutoconfiguration(WithMonologChannel::class, static function (ChildDefinition $definition, WithMonologChannel $attribute, \ReflectionParameter $reflector): void {
            $definition->addTag('monolog.logger', ['channel' => $attribute->channel, 'argument' => $reflector->getName()]);
        });
    }

    public function getXsdValidationBasePath(): string
    {
        return __DIR__.'/../../config/schema';
    }

    public function getNamespace(): string
    {
        return 'http://symfony.com/schema/dic/monolog';
    }

    private function buildHandler(ContainerBuilder $container, string $name, array $handler): string
    {
        $handlerId = $this->getHandlerId($name);
        $handlerType = $handler['type'];
        if ('service' === $handlerType) {
            $container->setAlias($handlerId, $handler['id']);

            if (!empty($handler['nested']) && true === $handler['nested']) {
                $this->markNestedHandler($handlerId);
            }

            return $handlerId;
        }

        if (\array_key_exists($handlerType, $this->handlerExtensions)) {
            $context = new HandlerContext($container, $handlerId, $handlerType, $this->markNestedHandler(...), $this->isHandlerDisabled(...));
            $definition = $this->handlerExtensions[$handlerType]->getDefinition($context, $handler, $handler[$handlerType] ?? []);
        } else {
            $handlerClass = $this->getHandlerClassByType($handlerType);
            $definition = new Definition($handlerClass);
        }

        if ($handler['include_stacktraces'] || null !== $handler['base_path']) {
            $configurator = new Definition(FormatterConfigurator::class, [
                $handler['include_stacktraces'],
                $handler['base_path'],
            ]);
            $definition->setConfigurator([$configurator, '__invoke']);
        }

        if (null === $handler['process_psr_3_messages']['enabled']) {
            $subNode = $handler[$handlerType] ?? [];
            $handler['process_psr_3_messages']['enabled'] = !isset($handler['handler']) && !$handler['members']
                && empty($subNode['handler'] ?? null) && empty($subNode['members'] ?? null);
        }

        if ($handler['process_psr_3_messages']['enabled'] && method_exists($definition->getClass(), 'pushProcessor')) {
            $processorId = $this->buildPsrLogMessageProcessor($container, $handler['process_psr_3_messages']);
            // The PSR-3 message processor must always be pushed first, before any
            // handler specific method call added by the handler extension.
            $methodCalls = $definition->getMethodCalls();
            array_unshift($methodCalls, ['pushProcessor', [new Reference($processorId)]]);
            $definition->setMethodCalls($methodCalls);
        }

        switch ($handlerType) {
            case 'elasticsearch':
                trigger_deprecation('symfony/monolog-bundle', '3.8', 'The "elasticsearch" handler type is deprecated in MonologBundle since version 3.8.0, use the "elastica" type instead, or switch to the official Elastic client using the "elastic_search" type.');
                // no break

            case 'elastica':
            case 'elastic_search':
                if (isset($handler['elasticsearch']['id'])) {
                    $client = new Reference($handler['elasticsearch']['id']);
                } else {
                    if ('elastic_search' === $handler['type']) {
                        // v8 has a new Elastic\ prefix
                        $client = new Definition(class_exists(\Elastic\Elasticsearch\Client::class) ? \Elastic\Elasticsearch\Client::class : \Elasticsearch\Client::class);
                        $factory = class_exists(\Elastic\Elasticsearch\ClientBuilder::class) ? \Elastic\Elasticsearch\ClientBuilder::class : \Elasticsearch\ClientBuilder::class;
                        $client->setFactory([$factory, 'fromConfig']);
                        $clientArguments = [
                            'hosts' => $handler['elasticsearch']['hosts'] ?? [$handler['elasticsearch']['host']],
                        ];

                        if (isset($handler['elasticsearch']['user'], $handler['elasticsearch']['password'])) {
                            $clientArguments['basicAuthentication'] = [$handler['elasticsearch']['user'], $handler['elasticsearch']['password']];
                        }
                    } else {
                        $client = new Definition(\Elastica\Client::class);

                        if (isset($handler['elasticsearch']['hosts'])) {
                            $clientArguments = [
                                'hosts' => $handler['elasticsearch']['hosts'],
                                'transport' => $handler['elasticsearch']['transport'],
                            ];
                        } else {
                            $clientArguments = [
                                'host' => $handler['elasticsearch']['host'],
                                'port' => $handler['elasticsearch']['port'],
                                'transport' => $handler['elasticsearch']['transport'],
                            ];
                        }

                        if (isset($handler['elasticsearch']['user'], $handler['elasticsearch']['password'])) {
                            $clientArguments['headers'] = [
                                'Authorization' => 'Basic '.base64_encode($handler['elasticsearch']['user'].':'.$handler['elasticsearch']['password']),
                            ];
                        }
                    }

                    $client->setArguments([
                        $clientArguments,
                    ]);

                    $client->setPublic(false);
                }

                // elastica handler definition
                $definition->setArguments([
                    $client,
                    [
                        'index' => $handler['index'],
                        'type' => $handler['document_type'],
                        'ignore_error' => $handler['ignore_error'],
                    ],
                    $handler['level'],
                    $handler['bubble'],
                ]);
                break;

            case 'redis':
            case 'predis':
                if (isset($handler['redis']['id'])) {
                    $clientId = $handler['redis']['id'];
                } elseif ('redis' === $handler['type']) {
                    if (!class_exists(\Redis::class)) {
                        throw new \RuntimeException('The \Redis class is not available.');
                    }

                    $client = new Definition(\Redis::class);
                    $client->addMethodCall('connect', [$handler['redis']['host'], $handler['redis']['port']]);
                    $client->addMethodCall('auth', [$handler['redis']['password']]);
                    $client->addMethodCall('select', [$handler['redis']['database']]);
                    $client->setPublic(false);
                    $clientId = uniqid('monolog.redis.client.', true);
                    $container->setDefinition($clientId, $client);
                } else {
                    if (!class_exists(\Predis\Client::class)) {
                        throw new \RuntimeException('The \Predis\Client class is not available.');
                    }

                    $client = new Definition(\Predis\Client::class);
                    $client->setArguments([
                        $handler['redis']['host'],
                    ]);
                    $client->setPublic(false);

                    $clientId = uniqid('monolog.predis.client.', true);
                    $container->setDefinition($clientId, $client);
                }
                $definition->setArguments([
                    new Reference($clientId),
                    $handler['redis']['key_name'],
                    $handler['level'],
                    $handler['bubble'],
                ]);
                break;

            default:
                if (!isset($this->handlerExtensions[$handlerType])) {
                    $nullWarning = '';
                    if ('' == $handlerType) {
                        $nullWarning = ', if you meant to define a null handler in a yaml config, make sure you quote "null" so it does not get converted to a php null';
                    }

                    throw new \InvalidArgumentException(\sprintf('Invalid handler type "%s" given for handler "%s".'.$nullWarning, $handler['type'], $name));
                }
        }

        if (!empty($handler['nested']) && true === $handler['nested']) {
            $this->markNestedHandler($handlerId);
        }

        if (!empty($handler['formatter'])) {
            $definition->addMethodCall('setFormatter', [new Reference($handler['formatter'])]);
        }

        if (!\in_array($handlerId, $this->nestedHandlers) && is_subclass_of($definition->getClass(), ResettableInterface::class)) {
            $definition->addTag('kernel.reset', ['method' => 'reset']);
        }

        // All handlers (including nested ones) are tagged so the handler manager can close them
        // on kernel shutdown. Unlike "kernel.reset", nested handlers must be closed too, as they
        // hold resources that are not released when their wrapper handler is reset.
        $definition->addTag('monolog.handler');

        $container->setDefinition($handlerId, $definition);

        return $handlerId;
    }

    private function markNestedHandler(string $nestedHandlerId): void
    {
        if (\in_array($nestedHandlerId, $this->nestedHandlers, true)) {
            return;
        }

        $this->nestedHandlers[] = $nestedHandlerId;
    }

    private function isHandlerDisabled(string $name): bool
    {
        return isset($this->disabledHandlers[$name]);
    }

    private function getHandlerId(string $name): string
    {
        return \sprintf('monolog.handler.%s', $name);
    }

    private function getHandlerClassByType(string $handlerType): string
    {
        return match ($handlerType) {
            'stream' => \Monolog\Handler\StreamHandler::class,
            'console' => \Symfony\Bridge\Monolog\Handler\ConsoleHandler::class,
            'group' => \Monolog\Handler\GroupHandler::class,
            'buffer' => \Monolog\Handler\BufferHandler::class,
            'deduplication' => \Monolog\Handler\DeduplicationHandler::class,
            'rotating_file' => \Monolog\Handler\RotatingFileHandler::class,
            'syslog' => \Monolog\Handler\SyslogHandler::class,
            'syslogudp' => \Monolog\Handler\SyslogUdpHandler::class,
            'null' => \Monolog\Handler\NullHandler::class,
            'test' => \Monolog\Handler\TestHandler::class,
            'gelf' => \Monolog\Handler\GelfHandler::class,
            'rollbar' => \Monolog\Handler\RollbarHandler::class,
            'flowdock' => \Monolog\Handler\FlowdockHandler::class,
            'browser_console' => \Monolog\Handler\BrowserConsoleHandler::class,
            'firephp' => \Symfony\Bridge\Monolog\Handler\FirePHPHandler::class,
            'chromephp' => \Symfony\Bridge\Monolog\Handler\ChromePhpHandler::class,
            'native_mailer' => \Monolog\Handler\NativeMailerHandler::class,
            'symfony_mailer' => \Symfony\Bridge\Monolog\Handler\MailerHandler::class,
            'socket' => \Monolog\Handler\SocketHandler::class,
            'pushover' => \Monolog\Handler\PushoverHandler::class,
            'newrelic' => \Monolog\Handler\NewRelicHandler::class,
            'slack' => \Monolog\Handler\SlackHandler::class,
            'slackwebhook' => \Monolog\Handler\SlackWebhookHandler::class,
            'cube' => \Monolog\Handler\CubeHandler::class,
            'amqp' => \Monolog\Handler\AmqpHandler::class,
            'error_log' => \Monolog\Handler\ErrorLogHandler::class,
            'loggly' => \Monolog\Handler\LogglyHandler::class,
            'logentries' => \Monolog\Handler\LogEntriesHandler::class,
            'whatfailuregroup' => \Monolog\Handler\WhatFailureGroupHandler::class,
            'fingers_crossed' => \Monolog\Handler\FingersCrossedHandler::class,
            'filter' => \Monolog\Handler\FilterHandler::class,
            'mongodb' => \Monolog\Handler\MongoDBHandler::class,
            'telegram' => \Monolog\Handler\TelegramBotHandler::class,
            'server_log' => \Symfony\Bridge\Monolog\Handler\ServerLogHandler::class,
            'redis', 'predis' => \Monolog\Handler\RedisHandler::class,
            'insightops' => \Monolog\Handler\InsightOpsHandler::class,
            'sampling' => \Monolog\Handler\SamplingHandler::class,
            'elastica' => \Monolog\Handler\ElasticaHandler::class,
            'elastic_search' => \Monolog\Handler\ElasticsearchHandler::class,
            'fallbackgroup' => \Monolog\Handler\FallbackGroupHandler::class,
            'noop' => \Monolog\Handler\NoopHandler::class,
            default => throw new \InvalidArgumentException(\sprintf('There is no handler class defined for handler "%s".', $handlerType)),
        };
    }

    private function buildPsrLogMessageProcessor(ContainerBuilder $container, array $processorOptions): string
    {
        $processorId = 'monolog.processor.psr_log_message';
        $processorArguments = [];

        unset($processorOptions['enabled']);

        if ($processorOptions) {
            $processorArguments = [
                $processorOptions['date_format'] ?? null,
                $processorOptions['remove_used_context_fields'] ?? false,
            ];
            $processorId .= '.'.ContainerBuilder::hash($processorArguments);
        }

        if (!$container->hasDefinition($processorId)) {
            $processor = new Definition(PsrLogMessageProcessor::class);
            $processor->setPublic(false);
            $processor->setArguments($processorArguments);
            $container->setDefinition($processorId, $processor);
        }

        return $processorId;
    }
}
