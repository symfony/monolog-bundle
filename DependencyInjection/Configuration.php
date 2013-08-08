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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * Possible handler types and related configurations (brackets indicate optional params):
 *
 * - service:
 *   - id
 *
 * - stream:
 *   - path: string
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - console:
 *   - [verbosity_levels]: level => verbosity configuration
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - firephp:
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - gelf:
 *   - publisher: {id: ...} or {hostname: ..., port: ..., chunk_size: ...}
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - chromephp:
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - rotating_file:
 *   - path: string
 *   - [max_files]: files to keep, defaults to zero (infinite)
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - fingers_crossed:
 *   - handler: the wrapped handler's name
 *   - [action_level|activation_strategy]: minimum level or service id to activate the handler, defaults to WARNING
 *   - [excluded_404s]: if set, the strategy will be changed to one that excludes 404s coming from URLs matching any of those patterns
 *   - [buffer_size]: defaults to 0 (unlimited)
 *   - [stop_buffering]: bool to disable buffering once the handler has been activated, defaults to true
 *   - [bubble]: bool, defaults to true
 *
 * - buffer:
 *   - handler: the wrapped handler's name
 *   - [buffer_size]: defaults to 0 (unlimited)
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - group:
 *   - members: the wrapped handlers by name
 *   - [bubble]: bool, defaults to true
 *
 * - syslog:
 *   - ident: string
 *   - [facility]: defaults to LOG_USER
 *   - [logopts]: defaults to LOG_PID
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - swift_mailer:
 *   - from_email: optional if email_prototype is given
 *   - to_email: optional if email_prototype is given
 *   - subject: optional if email_prototype is given
 *   - [email_prototype]: service id of a message, defaults to a default message with the three fields above
 *   - [mailer]: mailer service, defaults to mailer
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - native_mailer:
 *   - from_email: string
 *   - to_email: string
 *   - subject: string
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - socket:
 *   - connection_string: string
 *   - [timeout]: float
 *   - [connection_timeout]: float
 *   - [persistent]: bool
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - pushover:
 *   - token: pushover api token
 *   - user: user id or array of ids
 *   - [title]: optional title for messages, defaults to the server hostname
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - raven:
 *   - dsn: connection string
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - newrelic:
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - hipchat:
 *   - token: hipchat api token
 *   - room: room id or name
 *   - [notify]: defaults to false
 *   - [nickname]: defaults to Monolog
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - cube:
 *   - url: http/udp url to the cube server
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - amqp:
 *   - exchange: service id of an AMQPExchange
 *   - [exchange_name]: string, defaults to log
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - error_log:
 *   - [message_type]: int 0 or 4, defaults to 0
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - null:
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - test:
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - debug:
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('monolog');

        $rootNode
            ->fixXmlConfig('handler')
            ->children()
                ->arrayNode('channels')
                    ->canBeUnset()
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('handlers')
                    ->canBeUnset()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->fixXmlConfig('member')
                        ->canBeUnset()
                        ->children()
                            ->scalarNode('type')
                                ->isRequired()
                                ->treatNullLike('null')
                                ->beforeNormalization()
                                    ->always()
                                    ->then(function($v) { return strtolower($v); })
                                ->end()
                            ->end()
                            ->scalarNode('id')->end()
                            ->scalarNode('priority')->defaultValue(0)->end()
                            ->scalarNode('level')->defaultValue('DEBUG')->end()
                            ->booleanNode('bubble')->defaultTrue()->end()
                            ->scalarNode('path')->defaultValue('%kernel.logs_dir%/%kernel.environment%.log')->end() // stream and rotating
                            ->scalarNode('ident')->defaultFalse()->end() // syslog
                            ->scalarNode('logopts')->defaultValue(LOG_PID)->end() // syslog
                            ->scalarNode('facility')->defaultValue('user')->end() // syslog
                            ->scalarNode('max_files')->defaultValue(0)->end() // rotating
                            ->scalarNode('action_level')->defaultValue('WARNING')->end() // fingers_crossed
                            ->scalarNode('activation_strategy')->defaultNull()->end() // fingers_crossed
                            ->booleanNode('stop_buffering')->defaultTrue()->end()// fingers_crossed
                            ->arrayNode('excluded_404s') // fingers_crossed
                                ->canBeUnset()
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('buffer_size')->defaultValue(0)->end() // fingers_crossed and buffer
                            ->scalarNode('handler')->end() // fingers_crossed and buffer
                            ->scalarNode('url')->end() // cube
                            ->scalarNode('exchange')->end() // amqp
                            ->scalarNode('exchange_name')->defaultValue('log')->end() // amqp
                            ->scalarNode('room')->end() // hipchat
                            ->scalarNode('notify')->defaultFalse()->end() // hipchat
                            ->scalarNode('nickname')->defaultValue('Monolog')->end() // hipchat
                            ->scalarNode('token')->end() // pushover & hipchat
                            ->variableNode('user') // pushover
                                ->validate()
                                    ->ifTrue(function($v) {
                                        return !is_string($v) && !is_array($v);
                                    })
                                    ->thenInvalid('User must be a string or an array.')
                                ->end()
                            ->end()
                            ->scalarNode('title')->defaultNull()->end() // pushover
                            ->arrayNode('publisher')
                                ->canBeUnset()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function($v) { return array('id'=> $v); })
                                ->end()
                                ->children()
                                    ->scalarNode('id')->end()
                                    ->scalarNode('hostname')->end()
                                    ->scalarNode('port')->defaultValue(12201)->end()
                                    ->scalarNode('chunk_size')->defaultValue(1420)->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(function($v) {
                                        return !isset($v['id']) && !isset($v['hostname']);
                                    })
                                    ->thenInvalid('What must be set is either the hostname or the id.')
                                ->end()
                            ->end() // gelf
                            ->arrayNode('members') // group
                                ->canBeUnset()
                                ->performNoDeepMerging()
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('from_email')->end() // swift_mailer and native_mailer
                            ->arrayNode('to_email') // swift_mailer and native_mailer
                                ->prototype('scalar')->end()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function($v) { return array($v); })
                                ->end()
                            ->end()
                            ->scalarNode('subject')->end() // swift_mailer and native_mailer
                            ->scalarNode('content_type')->defaultNull()->end() // swift_mailer
                            ->scalarNode('mailer')->defaultValue('mailer')->end() // swift_mailer
                            ->arrayNode('email_prototype') // swift_mailer
                                ->canBeUnset()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function($v) { return array('id' => $v); })
                                ->end()
                                ->children()
                                    ->scalarNode('id')->isRequired()->end()
                                    ->scalarNode('method')->defaultNull()->end()
                                ->end()
                            ->end()
                            ->scalarNode('connection_string')->end() // socket_handler
                            ->scalarNode('timeout')->end() // socket_handler
                            ->scalarNode('connection_timeout')->end() // socket_handler
                            ->booleanNode('persistent')->end() // socket_handler
                            ->scalarNode('dsn')->end() // raven_handler
                            ->scalarNode('message_type')->defaultValue(0)->end() // error_log
                            ->arrayNode('verbosity_levels') // console
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(function ($v) {
                                        $map = array();
                                        $verbosities = array('VERBOSITY_NORMAL', 'VERBOSITY_VERBOSE', 'VERBOSITY_VERY_VERBOSE', 'VERBOSITY_DEBUG');
                                        // allow numeric indexed array with ascendning verbosity and lowercase names of the constants
                                        foreach ($v as $verbosity => $level) {
                                            if (is_int($verbosity) && isset($verbosities[$verbosity])) {
                                                $map[$verbosities[$verbosity]] = strtoupper($level);
                                            } else {
                                                $map[strtoupper($verbosity)] = strtoupper($level);
                                            }
                                        }

                                        return $map;
                                    })
                                ->end()
                                ->children()
                                    ->scalarNode('VERBOSITY_NORMAL')->defaultValue('WARNING')->end()
                                    ->scalarNode('VERBOSITY_VERBOSE')->defaultValue('NOTICE')->end()
                                    ->scalarNode('VERBOSITY_VERY_VERBOSE')->defaultValue('INFO')->end()
                                    ->scalarNode('VERBOSITY_DEBUG')->defaultValue('DEBUG')->end()
                                ->end()
                                ->validate()
                                    ->always(function ($v) {
                                        $map = array();
                                        foreach ($v as $verbosity => $level) {
                                            $verbosityConstant = 'Symfony\Component\Console\Output\OutputInterface::'.$verbosity;

                                            if (!defined($verbosityConstant)) {
                                                throw new InvalidConfigurationException(sprintf(
                                                    'The configured verbosity "%s" is invalid as it is not defined in Symfony\Component\Console\Output\OutputInterface.',
                                                     $verbosity
                                                ));
                                            }
                                            if (!is_numeric($level)) {
                                                $levelConstant = 'Monolog\Logger::'.$level;
                                                if (!defined($levelConstant)) {
                                                    throw new InvalidConfigurationException(sprintf(
                                                        'The configured minimum log level "%s" for verbosity "%s" is invalid as it is not defined in Monolog\Logger.',
                                                         $level, $verbosity
                                                    ));
                                                }
                                                $level = constant($levelConstant);
                                            } else {
                                                $level = (int) $level;
                                            }

                                            $map[constant($verbosityConstant)] = $level;
                                        }

                                        return $map;
                                    })
                                ->end()
                            ->end()
                            ->arrayNode('channels')
                                ->fixXmlConfig('channel', 'elements')
                                ->canBeUnset()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function($v) { return array('elements' => array($v)); })
                                ->end()
                                ->beforeNormalization()
                                    ->ifTrue(function($v) { return is_array($v) && is_numeric(key($v)); })
                                    ->then(function($v) { return array('elements' => $v); })
                                ->end()
                                ->validate()
                                    ->ifTrue(function($v) { return empty($v); })
                                    ->thenUnset()
                                ->end()
                                ->validate()
                                    ->always(function ($v) {
                                        $isExclusive = null;
                                        if (isset($v['type'])) {
                                            $isExclusive = 'exclusive' === $v['type'];
                                        }

                                        $elements = array();
                                        foreach ($v['elements'] as $element) {
                                            if (0 === strpos($element, '!')) {
                                                if (false === $isExclusive) {
                                                    throw new InvalidConfigurationException('Cannot combine exclusive/inclusive definitions in channels list.');
                                                }
                                                $elements[] = substr($element, 1);
                                                $isExclusive = true;
                                            } else {
                                                if (true === $isExclusive) {
                                                    throw new InvalidConfigurationException('Cannot combine exclusive/inclusive definitions in channels list');
                                                }
                                                $elements[] = $element;
                                                $isExclusive = false;
                                            }
                                        }

                                        return array('type' => $isExclusive ? 'exclusive' : 'inclusive', 'elements' => $elements);
                                    })
                                ->end()
                                ->children()
                                    ->scalarNode('type')
                                        ->validate()
                                            ->ifNotInArray(array('inclusive', 'exclusive'))
                                            ->thenInvalid('The type of channels has to be inclusive or exclusive')
                                        ->end()
                                    ->end()
                                    ->arrayNode('elements')
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('formatter')->end()
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'service' === $v['type'] && !empty($v['formatter']); })
                            ->thenInvalid('Service handlers can not have a formatter configured in the bundle, you must reconfigure the service itself instead')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return ('fingers_crossed' === $v['type'] || 'buffer' === $v['type']) && 1 !== count($v['handler']); })
                            ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler or BufferHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'fingers_crossed' === $v['type'] && !empty($v['excluded_404s']) && !empty($v['activation_strategy']); })
                            ->thenInvalid('You can not use excluded_404s together with a custom activation_strategy in a FingersCrossedHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'swift_mailer' === $v['type'] && empty($v['email_prototype']) && (empty($v['from_email']) || empty($v['to_email']) || empty($v['subject'])); })
                            ->thenInvalid('The sender, recipient and subject or an email prototype have to be specified to use a SwiftMailerHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'native_mailer' === $v['type'] && (empty($v['from_email']) || empty($v['to_email']) || empty($v['subject'])); })
                            ->thenInvalid('The sender, recipient and subject have to be specified to use a NativeMailerHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'service' === $v['type'] && !isset($v['id']); })
                            ->thenInvalid('The id has to be specified to use a service as handler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'gelf' === $v['type'] && !isset($v['publisher']); })
                            ->thenInvalid('The publisher has to be specified to use a GelfHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'socket' === $v['type'] && !isset($v['connection_string']); })
                            ->thenInvalid('The connection_string has to be specified to use a SocketHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'pushover' === $v['type'] && (empty($v['token']) || empty($v['user'])); })
                            ->thenInvalid('The token and user have to be specified to use a PushoverHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'raven' === $v['type'] && !array_key_exists('dsn', $v); })
                            ->thenInvalid('The DSN has to be specified to use a RavenHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'hipchat' === $v['type'] && (empty($v['token']) || empty($v['room'])); })
                            ->thenInvalid('The token and room have to be specified to use a HipChatHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'cube' === $v['type'] && empty($v['url']); })
                            ->thenInvalid('The url has to be specified to use a CubeHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'amqp' === $v['type'] && empty($v['exchange']); })
                            ->thenInvalid('The exchange has to be specified to use a AmqpHandler')
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(function($v) { return isset($v['debug']); })
                        ->thenInvalid('The "debug" name cannot be used as it is reserved for the handler of the profiler')
                    ->end()
                    ->example(array(
                        'syslog' => array(
                            'type' => 'stream',
                            'path' => '/var/log/symfony.log',
                            'level' => 'ERROR',
                            'bubble' => 'false',
                            'formatter' => 'my_formatter',
                            'processors' => array('some_callable')
                            ),
                        'main' => array(
                            'type' => 'fingers_crossed',
                            'action_level' => 'WARNING',
                            'buffer_size' => 30,
                            'handler' => 'custom',
                            ),
                        'custom' => array(
                            'type' => 'service',
                            'id' => 'my_handler'
                            )
                        ))
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
