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

/**
 * MonologExtension is an extension for the Monolog library.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 */
class MonologExtension extends Extension
{
    private $nestedHandlers = array();

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
                    'id'       => $this->buildHandler($container, $name, $handler),
                    'channels' => isset($handler['channels']) ? $handler['channels'] : null
                );
            }

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
        $handler['level'] = is_int($handler['level']) ? $handler['level'] : constant('Monolog\Logger::'.strtoupper($handler['level']));

        switch ($handler['type']) {
        case 'service':
            $container->setAlias($handlerId, $handler['id']);

            return $handlerId;

        case 'stream':
            $definition->setArguments(array(
                $handler['path'],
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        case 'console':
            if (!class_exists('Symfony\Bridge\Monolog\Handler\ConsoleHandler')) {
                throw new \RuntimeException('The console handler requires symfony/monolog-bridge 2.4+');
            }

            $definition->setArguments(array(
                null,
                $handler['bubble'],
                isset($handler['verbosity_levels']) ? $handler['verbosity_levels'] : array()
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
            } else {
                $publisher = new Definition("%monolog.gelf.publisher.class%", array(
                    $handler['publisher']['hostname'],
                    $handler['publisher']['port'],
                    $handler['publisher']['chunk_size'],
                ));

                $publisherId = 'monolog.gelf.publisher';
                $publisher->setPublic(false);
                $container->setDefinition($publisherId, $publisher);
            }

            $definition->setArguments(array(
                new Reference($publisherId),
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
            ));
            break;

        case 'fingers_crossed':
            $handler['action_level'] = is_int($handler['action_level']) ? $handler['action_level'] : constant('Monolog\Logger::'.strtoupper($handler['action_level']));
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
            ));
            break;

        case 'group':
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

        case 'swift_mailer':
            $oldHandler = false;
            // fallback for older symfony versions that don't have the new SwiftMailerHandler in the bridge
            if (!class_exists($definition->getClass())) {
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
                $message = new Definition('Swift_Message');
                $message->setFactoryService('mailer');
                $message->setFactoryMethod('createMessage');
                $message->setPublic(false);
                $message->addMethodCall('setFrom', array($handler['from_email']));
                $message->addMethodCall('setTo', array($handler['to_email']));
                $message->addMethodCall('setSubject', array($handler['subject']));

                if (isset($handler['content_type'])) {
                    $message->addMethodCall('setContentType', array($handler['content_type']));
                }

                $messageId = sprintf('%s.mail_prototype', $handlerId);
                $container->setDefinition($messageId, $message);
                $prototype = new Reference($messageId);
            }
            $definition->setArguments(array(
                new Reference($handler['mailer']),
                $prototype,
                $handler['level'],
                $handler['bubble'],
            ));
            if (!$oldHandler) {
                $definition->addMethodCall('setTransport', array(new Reference('swiftmailer.transport.real')));
                $definition->addTag('kernel.event_listener', array('event' => 'kernel.terminate', 'method' => 'onKernelTerminate'));
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
            $clientId = 'monolog.raven.client.' . sha1($handler['dsn']);
            if (!$container->hasDefinition($clientId)) {
                $client = new Definition("Raven_Client", array(
                    $handler['dsn']
                ));
                $client->setPublic(false);
                $container->setDefinition($clientId, $client);
            }
            $definition->setArguments(array(
                new Reference($clientId),
                $handler['level'],
                $handler['bubble'],
            ));
            break;

        // Handlers using the constructor of AbstractHandler without adding their own arguments
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
