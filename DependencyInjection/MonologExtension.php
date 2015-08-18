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

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Elastica\Client;

/**
 * MonologExtension is an extension for the Monolog library.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 */
class MonologExtension extends Extension
{
    private $nestedHandlers = array();

    private $swiftMailerHandlers = array();

    private function levelToMonologConst($level)
    {
        return is_int($level) ? $level : constant('Monolog\Logger::'.strtoupper($level));
    }

    /**
     * Loads the Monolog configuration.
     *
     * @param array            $configs   An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (isset($config['handlers'])) {
            $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('monolog.xml');
            $container->setAlias('logger', 'monolog.logger');

            $handlers = array();

            foreach ($config['handlers'] as $name => $handler) {
                $handlers[$handler['priority']][] = array(
                    'id' => $this->buildHandler($container, $name, $handler),
                    'channels' => empty($handler['channels']) ? null : $handler['channels'],
                );
            }

            $container->setParameter(
                'monolog.swift_mailer.handlers',
                $this->swiftMailerHandlers
            );

            ksort($handlers);
            $sortedHandlers = array();
            foreach ($handlers as $priorityHandlers) {
                foreach (array_reverse($priorityHandlers) as $handler) {
                    $sortedHandlers[] = $handler;
                }
            }

            $handlersToChannels = array();
            foreach ($sortedHandlers as $handler) {
                if (!in_array($handler['id'], $this->nestedHandlers)) {
                    $handlersToChannels[$handler['id']] = $handler['channels'];
                }
            }
            $container->setParameter('monolog.handlers_to_channels', $handlersToChannels);

            $this->addClassesToCompile(array(
                'Monolog\\Formatter\\FormatterInterface',
                'Monolog\\Formatter\\LineFormatter',
                'Monolog\\Handler\\HandlerInterface',
                'Monolog\\Handler\\AbstractHandler',
                'Monolog\\Handler\\AbstractProcessingHandler',
                'Monolog\\Handler\\StreamHandler',
                'Monolog\\Handler\\FingersCrossedHandler',
                'Monolog\\Handler\\FilterHandler',
                'Monolog\\Handler\\TestHandler',
                'Monolog\\Logger',
                'Symfony\\Bridge\\Monolog\\Logger',
                'Symfony\\Bridge\\Monolog\\Handler\\DebugHandler',
                'Monolog\\Handler\\FingersCrossed\\ActivationStrategyInterface',
                'Monolog\\Handler\\FingersCrossed\\ErrorLevelActivationStrategy',
            ));
        }

        $container->setParameter('monolog.additional_channels', isset($config['channels']) ? $config['channels'] : array());
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/monolog';
    }

    private function buildHandler(ContainerBuilder $container, $name, array $handler)
    {
        $handlerId = $this->getHandlerId($name);
        $definition = new Definition(sprintf('%%monolog.handler.%s.class%%', $handler['type']));
        $handler['level'] = $this->levelToMonologConst($handler['level']);

        switch ($handler['type']) {
        case 'service':
            $container->setAlias($handlerId, $handler['id']);

            return $handlerId;

        case 'stream':
            $definition->setArguments(array(
                $handler['path'],
                $handler['level'],
                $handler['bubble'],
                $handler['file_permission'],
            ));
            break;

        case 'console':
            if (!class_exists('Symfony\Bridge\Monolog\Handler\ConsoleHandler')) {
                throw new \RuntimeException('The console handler requires symfony/monolog-bridge 2.4+');
            }

            $definition->setArguments(array(
                null,
                $handler['bubble'],
                isset($handler['verbosity_levels']) ? $handler['verbosity_levels'] : array(),
            ));
            $definition->addTag('kernel.event_subscriber');
            break;

        case 'firephp':
            $definition->setArguments(array(
                $handler['level'],
                $handler['bubble'],
            ));
            $definition->addTag('kernel.event_listener', array('event' => 'kernel.response', 'method' => 'onKernelResponse'));
            break;

        case 'gelf':
            if (isset($handler['publisher']['id'])) {
                $publisherId = $handler['publisher']['id'];
            } elseif (class_exists('Gelf\Transport\UdpTransport')) {
                $transport = new Definition("Gelf\Transport\UdpTransport", array(
                    $handler['publisher']['hostname'],
                    $handler['publisher']['port'],
                    $handler['publisher']['chunk_size'],
                ));
                $transportId = uniqid('monolog.gelf.transport.');
                $transport->setPublic(false);
                $container->setDefinition($transportId, $transport);

                $publisher = new Definition('%monolog.gelfphp.publisher.class%', array());
                $publisher->addMethodCall('addTransport', array(new Reference($transportId)));
                $publisherId = uniqid('monolog.gelf.publisher.');
                $publisher->setPublic(false);
                $container->setDefinition($publisherId, $publisher);
            } elseif (class_exists('Gelf\MessagePublisher')) {
                $publisher = new Definition('%monolog.gelf.publisher.class%', array(
                    $handler['publisher']['hostname'],
                    $handler['publisher']['port'],
                    $handler['publisher']['chunk_size'],
                ));

                $publisherId = uniqid('monolog.gelf.publisher.');
                $publisher->setPublic(false);
                $container->setDefinition($publisherId, $publisher);
            } else {
                throw new \RuntimeException('The gelf handler requires the graylog2/gelf-php package to be installed');
            }

            $definition->setArguments(array(
                new Reference($publisherId),
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'mongo':
            if (isset($handler['mongo']['id'])) {
                $clientId = $handler['mongo']['id'];
            } else {
                $server = 'mongodb://';

                if (isset($handler['mongo']['user'])) {
                    $server .= $handler['mongo']['user'].':'.$handler['mongo']['pass'].'@';
                }

                $server .= $handler['mongo']['host'].':'.$handler['mongo']['port'];

                $client = new Definition('%monolog.mongo.client.class%', array(
                    $server,
                ));

                $clientId = uniqid('monolog.mongo.client.');
                $client->setPublic(false);
                $container->setDefinition($clientId, $client);
            }

            $definition->setArguments(array(
                new Reference($clientId),
                $handler['mongo']['database'],
                $handler['mongo']['collection'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'elasticsearch':
            if (isset($handler['elasticsearch']['id'])) {
                $clientId = $handler['elasticsearch']['id'];
            } else {
                // elastica client new definition
                $elasticaClient = new Definition('%monolog.elastica.client.class%');
                $elasticaClient->setArguments(array(
                    array(
                        'host' => $handler['elasticsearch']['host'],
                        'port' => $handler['elasticsearch']['port'],
                    ),
                ));

                $clientId = uniqid('monolog.elastica.client.');
                $elasticaClient->setPublic(false);
                $container->setDefinition($clientId, $elasticaClient);
            }

            // elastica handler definition
            $definition->setArguments(array(
                new Reference($clientId),
                array(
                    'index' => $handler['index'],
                    'type' => $handler['document_type'],
                ),
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'chromephp':
            $definition->setArguments(array(
                $handler['level'],
                $handler['bubble'],
            ));
            $definition->addTag('kernel.event_listener', array('event' => 'kernel.response', 'method' => 'onKernelResponse'));
            break;

        case 'rotating_file':
            $definition->setArguments(array(
                $handler['path'],
                $handler['max_files'],
                $handler['level'],
                $handler['bubble'],
                $handler['file_permission'],
            ));
            break;

        case 'fingers_crossed':
            $handler['action_level'] = $this->levelToMonologConst($handler['action_level']);
            if (null !== $handler['passthru_level']) {
                $handler['passthru_level'] = $this->levelToMonologConst($handler['passthru_level']);
            }
            $nestedHandlerId = $this->getHandlerId($handler['handler']);
            $this->nestedHandlers[] = $nestedHandlerId;

            if (isset($handler['activation_strategy'])) {
                $activation = new Reference($handler['activation_strategy']);
            } elseif (!empty($handler['excluded_404s'])) {
                $activationDef = new Definition('%monolog.activation_strategy.not_found.class%', array($handler['excluded_404s'], $handler['action_level']));
                $activationDef->addMethodCall('setRequest', array(new Reference('request', ContainerInterface::NULL_ON_INVALID_REFERENCE, false)));
                $container->setDefinition($handlerId.'.not_found_strategy', $activationDef);
                $activation = new Reference($handlerId.'.not_found_strategy');
            } else {
                $activation = $handler['action_level'];
            }

            $definition->setArguments(array(
                new Reference($nestedHandlerId),
                $activation,
                $handler['buffer_size'],
                $handler['bubble'],
                $handler['stop_buffering'],
                $handler['passthru_level'],
            ));
            break;

        case 'filter':
            $handler['min_level'] = $this->levelToMonologConst($handler['min_level']);
            $handler['max_level'] = $this->levelToMonologConst($handler['max_level']);
            foreach (array_keys($handler['accepted_levels']) as $k) {
                $handler['accepted_levels'][$k] = $this->levelToMonologConst($handler['accepted_levels'][$k]);
            }

            $nestedHandlerId = $this->getHandlerId($handler['handler']);
            $this->nestedHandlers[] = $nestedHandlerId;
            $minLevelOrList = !empty($handler['accepted_levels']) ? $handler['accepted_levels'] : $handler['min_level'];

            $definition->setArguments(array(
                new Reference($nestedHandlerId),
                $minLevelOrList,
                $handler['max_level'],
                $handler['bubble'],
            ));
            break;

        case 'buffer':
            $nestedHandlerId = $this->getHandlerId($handler['handler']);
            $this->nestedHandlers[] = $nestedHandlerId;

            $definition->setArguments(array(
                new Reference($nestedHandlerId),
                $handler['buffer_size'],
                $handler['level'],
                $handler['bubble'],
                $handler['flush_on_overflow'],
            ));
            break;

        case 'group':
        case 'whatfailuregroup':
            $references = array();
            foreach ($handler['members'] as $nestedHandler) {
                $nestedHandlerId = $this->getHandlerId($nestedHandler);
                $this->nestedHandlers[] = $nestedHandlerId;
                $references[] = new Reference($nestedHandlerId);
            }

            $definition->setArguments(array(
                $references,
                $handler['bubble'],
            ));
            break;

        case 'syslog':
            $definition->setArguments(array(
                $handler['ident'],
                $handler['facility'],
                $handler['level'],
                $handler['bubble'],
                $handler['logopts'],
            ));
            break;

        case 'syslogudp':
            $definition->setArguments(array(
                $handler['host'],
                $handler['port'],
                $handler['facility'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'swift_mailer':
            $oldHandler = false;
            // fallback for older symfony versions that don't have the new SwiftMailerHandler in the bridge
            $newHandlerClass = $container->getParameterBag()->resolveValue($definition->getClass());
            if (!class_exists($newHandlerClass)) {
                $definition = new Definition('Monolog\Handler\SwiftMailerHandler');
                $oldHandler = true;
            }

            if (isset($handler['email_prototype'])) {
                if (!empty($handler['email_prototype']['method'])) {
                    $prototype = array(new Reference($handler['email_prototype']['id']), $handler['email_prototype']['method']);
                } else {
                    $prototype = new Reference($handler['email_prototype']['id']);
                }
            } else {
                $messageFactory = new Definition('Symfony\Bundle\MonologBundle\SwiftMailer\MessageFactory');
                $messageFactory->setLazy(true);
                $messageFactory->setPublic(false);
                $messageFactory->setArguments(array(
                    new Reference($handler['mailer']),
                    $handler['from_email'],
                    $handler['to_email'],
                    $handler['subject'],
                    $handler['content_type']
                ));

                $messageFactoryId = sprintf('%s.mail_message_factory', $handlerId);
                $container->setDefinition($messageFactoryId, $messageFactory);
                // set the prototype as a callable
                $prototype = array(new Reference($messageFactoryId), 'createMessage');
            }
            $definition->setArguments(array(
                new Reference($handler['mailer']),
                $prototype,
                $handler['level'],
                $handler['bubble'],
            ));
            if (!$oldHandler) {
                $this->swiftMailerHandlers[] = $handlerId;
                $definition->addTag('kernel.event_listener', array('event' => 'kernel.terminate', 'method' => 'onKernelTerminate'));
                if (method_exists($newHandlerClass, 'onCliTerminate')) {
                    $definition->addTag('kernel.event_listener', array('event' => 'console.terminate', 'method' => 'onCliTerminate'));
                }
            }
            break;

        case 'native_mailer':
            $definition->setArguments(array(
                $handler['to_email'],
                $handler['subject'],
                $handler['from_email'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'socket':
            $definition->setArguments(array(
                $handler['connection_string'],
                $handler['level'],
                $handler['bubble'],
            ));
            if (isset($handler['timeout'])) {
                $definition->addMethodCall('setTimeout', array($handler['timeout']));
            }
            if (isset($handler['connection_timeout'])) {
                $definition->addMethodCall('setConnectionTimeout', array($handler['connection_timeout']));
            }
            if (isset($handler['persistent'])) {
                $definition->addMethodCall('setPersistent', array($handler['persistent']));
            }
            break;

        case 'pushover':
            $definition->setArguments(array(
                $handler['token'],
                $handler['user'],
                $handler['title'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'hipchat':
            $definition->setArguments(array(
                $handler['token'],
                $handler['room'],
                $handler['nickname'],
                $handler['notify'],
                $handler['level'],
                $handler['bubble'],
                $handler['use_ssl'],
                $handler['message_format'],
            ));
            break;

        case 'slack':
            $definition->setArguments(array(
                $handler['token'],
                $handler['channel'],
                $handler['bot_name'],
                $handler['use_attachment'],
                $handler['icon_emoji'],
                $handler['level'],
                $handler['bubble'],
                $handler['use_short_attachment'],
                $handler['include_extra'],
            ));
            break;

        case 'cube':
            $definition->setArguments(array(
                $handler['url'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'amqp':
            $definition->setArguments(array(
                new Reference($handler['exchange']),
                $handler['exchange_name'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'error_log':
            $definition->setArguments(array(
                $handler['message_type'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'raven':
            if (null !== $handler['client_id']) {
                $clientId = $handler['client_id'];
            } else {
                $client = new Definition('Raven_Client', array(
                    $handler['dsn'],
                ));
                $client->setPublic(false);
                $clientId = 'monolog.raven.client.'.sha1($handler['dsn']);
                $container->setDefinition($clientId, $client);
            }
            $definition->setArguments(array(
                new Reference($clientId),
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'loggly':
            $definition->setArguments(array(
                $handler['token'],
                $handler['level'],
                $handler['bubble'],
            ));
            if (!empty($handler['tags'])) {
                $definition->addMethodCall('setTag', array(implode(',', $handler['tags'])));
            }
            break;

        case 'logentries':
            $definition->setArguments(array(
                $handler['token'],
                $handler['use_ssl'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'flowdock':
            $definition->setArguments(array(
                $handler['token'],
                $handler['level'],
                $handler['bubble'],
            ));

            if (empty($handler['formatter'])) {
                $formatter = new Definition("Monolog\Formatter\FlowdockFormatter", array(
                    $handler['source'],
                    $handler['from_email'],
                ));
                $formatterId = 'monolog.flowdock.formatter.'.sha1($handler['source'].'|'.$handler['from_email']);
                $formatter->setPublic(false);
                $container->setDefinition($formatterId, $formatter);

                $definition->addMethodCall('setFormatter', array(new Reference($formatterId)));
            }
            break;

        case 'rollbar':
            if (!empty($handler['id'])) {
                $rollbarId = $handler['id'];
            } else {
                $config = $handler['config'] ?: array();
                $config['access_token'] = $handler['token'];
                $rollbar = new Definition('RollbarNotifier', array(
                    $config,
                ));
                $rollbarId = 'monolog.rollbar.notifier.'.sha1(json_encode($config));
                $rollbar->setPublic(false);
                $container->setDefinition($rollbarId, $rollbar);
            }

            $definition->setArguments(array(
                new Reference($rollbarId),
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        // Handlers using the constructor of AbstractHandler without adding their own arguments
        case 'browser_console':
        case 'newrelic':
        case 'test':
        case 'null':
        case 'debug':
            $definition->setArguments(array(
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        default:
            throw new \InvalidArgumentException(sprintf('Invalid handler type "%s" given for handler "%s"', $handler['type'], $name));
        }

        if (!empty($handler['formatter'])) {
            $definition->addMethodCall('setFormatter', array(new Reference($handler['formatter'])));
        }
        $container->setDefinition($handlerId, $definition);

        return $handlerId;
    }

    private function getHandlerId($name)
    {
        return sprintf('monolog.handler.%s', $name);
    }
}
