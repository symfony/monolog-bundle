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
                            ->scalarNode('buffer_size')->defaultValue(0)->end() // fingers_crossed and buffer
                            ->scalarNode('handler')->end() // fingers_crossed and buffer
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
                            ->arrayNode('email_prototype') // swift_mailer
                                ->canBeUnset()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function($v) { return array('id' => $v); })
                                ->end()
                                ->children()
                                    ->scalarNode('id')->isRequired()->end()
                                    ->scalarNode('factory-method')->defaultNull()->end()
                                ->end()
                            ->end()
                            ->scalarNode('connection_string')->end() // socket_handler
                            ->scalarNode('timeout')->end() // socket_handler
                            ->scalarNode('connection_timeout')->end() // socket_handler
                            ->booleanNode('persistent')->end() // socket_handler
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
                            ->ifTrue(function($v) { return ('fingers_crossed' === $v['type'] || 'buffer' === $v['type']) && 1 !== count($v['handler']); })
                            ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler or BufferHandler')
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
