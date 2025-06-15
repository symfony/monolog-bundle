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

use Monolog\Logger;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class contains the configuration information for the bundle.
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
 *   - [file_permission]: int|null, defaults to null (0644)
 *   - [use_locking]: bool, defaults to false
 *
 * - console:
 *   - [verbosity_levels]: level => verbosity configuration
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [console_formatter_options]: array
 *
 * - firephp:
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - browser_console:
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
 *   - [file_permission]: string|null, defaults to null
 *   - [use_locking]: bool, defaults to false
 *   - [filename_format]: string, defaults to '{filename}-{date}'
 *   - [date_format]: string, defaults to 'Y-m-d'
 *
 * - mongo:
 *   - mongo:
 *      - id: optional if host is given
 *      - host: database host name, optional if id is given
 *      - [port]: defaults to 27017
 *      - [user]: database user name
 *      - pass: mandatory only if user is present
 *      - [database]: defaults to monolog
 *      - [collection]: defaults to logs
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - elastic_search:
 *   - elasticsearch:
 *      - id: optional if host is given
 *      - host: elastic search host name, with scheme (e.g. "https://127.0.0.1:9200")
 *      - [user]: elastic search user name
 *      - [password]: elastic search user password
 *   - [index]: index name, defaults to monolog
 *   - [document_type]: document_type, defaults to logs
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - elastica:
 *   - elasticsearch:
 *      - id: optional if host is given
 *      - host: elastic search host name. Do not prepend with http(s)://
 *      - [port]: defaults to 9200
 *      - [transport]: transport protocol (http by default)
 *      - [user]: elastic search user name
 *      - [password]: elastic search user password
 *   - [index]: index name, defaults to monolog
 *   - [document_type]: document_type, defaults to logs
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - redis:
 *   - redis:
 *      - id: optional if host is given
 *      - host: 127.0.0.1
 *      - password: null
 *      - port: 6379
 *      - database: 0
 *      - key_name: monolog_redis
 *
 * - predis:
 *   - redis:
 *      - id: optional if host is given
 *      - host: tcp://10.0.0.1:6379
 *      - key_name: monolog_redis
 *
 * - fingers_crossed:
 *   - handler: the wrapped handler's name
 *   - [action_level|activation_strategy]: minimum level or service id to activate the handler, defaults to WARNING
 *   - [excluded_404s]: if set, the strategy will be changed to one that excludes 404s coming from URLs matching any of those patterns
 *   - [excluded_http_codes]: if set, the strategy will be changed to one that excludes specific HTTP codes (requires Symfony Monolog bridge 4.1+)
 *   - [buffer_size]: defaults to 0 (unlimited)
 *   - [stop_buffering]: bool to disable buffering once the handler has been activated, defaults to true
 *   - [passthru_level]: level name or int value for messages to always flush, disabled by default
 *   - [bubble]: bool, defaults to true
 *
 * - filter:
 *   - handler: the wrapped handler's name
 *   - [accepted_levels]: list of levels to accept
 *   - [min_level]: minimum level to accept (only used if accepted_levels not specified)
 *   - [max_level]: maximum level to accept (only used if accepted_levels not specified)
 *   - [bubble]: bool, defaults to true
 *
 * - buffer:
 *   - handler: the wrapped handler's name
 *   - [buffer_size]: defaults to 0 (unlimited)
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [flush_on_overflow]: bool, defaults to false
 *
 * - deduplication:
 *   - handler: the wrapped handler's name
 *   - [store]: The file/path where the deduplication log should be kept, defaults to %kernel.cache_dir%/monolog_dedup_*
 *   - [deduplication_level]: The minimum logging level for log records to be looked at for deduplication purposes, defaults to ERROR
 *   - [time]: The period (in seconds) during which duplicate entries should be suppressed after a given log is sent through, defaults to 60
 *   - [bubble]: bool, defaults to true
 *
 * - group:
 *   - members: the wrapped handlers by name
 *   - [bubble]: bool, defaults to true
 *
 * - whatfailuregroup:
 *   - members: the wrapped handlers by name
 *   - [bubble]: bool, defaults to true
 *
 * - fallbackgroup
 *   - members: the wrapped handlers by name
 *   - [bubble]: bool, defaults to true
 *
 * - syslog:
 *   - ident: string
 *   - [facility]: defaults to 'user', use any of the LOG_* facility constant but without LOG_ prefix, e.g. user for LOG_USER
 *   - [logopts]: defaults to LOG_PID
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - syslogudp:
 *   - host: syslogd host name
 *   - [port]: defaults to 514
 *   - [facility]: defaults to 'user', use any of the LOG_* facility constant but without LOG_ prefix, e.g. user for LOG_USER
 *   - [logopts]: defaults to LOG_PID
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [ident]: string, defaults to
 *
 * - swift_mailer:
 *   - from_email: optional if email_prototype is given
 *   - to_email: optional if email_prototype is given
 *   - subject: optional if email_prototype is given
 *   - [email_prototype]: service id of a message, defaults to a default message with the three fields above
 *   - [content_type]: optional if email_prototype is given, defaults to text/plain
 *   - [mailer]: mailer service, defaults to mailer
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [lazy]: use service lazy loading, bool, defaults to true
 *
 * - native_mailer:
 *   - from_email: string
 *   - to_email: string
 *   - subject: string
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [headers]: optional array containing additional headers: ['Foo: Bar', '...']
 *
 * - symfony_mailer:
 *   - from_email: optional if email_prototype is given
 *   - to_email: optional if email_prototype is given
 *   - subject: optional if email_prototype is given
 *   - [email_prototype]: service id of a message, defaults to a default message with the three fields above
 *   - [mailer]: mailer service id, defaults to mailer.mailer
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
 *   - [timeout]: float
 *   - [connection_timeout]: float
 *
 * - raven / sentry:
 *   - dsn: connection string
 *   - client_id: Raven client custom service id (optional)
 *   - [release]: release number of the application that will be attached to logs, defaults to null
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [auto_log_stacks]: bool, defaults to false
 *   - [environment]: string, default to null (no env specified)
 *
 * - sentry:
 *   - hub_id: Sentry hub custom service id (optional)
 *   - [fill_extra_context]: bool, defaults to false
 *
 * - newrelic:
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [app_name]: new relic app name, default null
 *
 * - hipchat:
 *   - token: hipchat api token
 *   - room: room id or name
 *   - [notify]: defaults to false
 *   - [nickname]: defaults to Monolog
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [use_ssl]: bool, defaults to true
 *   - [message_format]: text or html, defaults to text
 *   - [host]: defaults to "api.hipchat.com"
 *   - [api_version]: defaults to "v1"
 *   - [timeout]: float
 *   - [connection_timeout]: float
 *
 * - slack:
 *   - token: slack api token
 *   - channel: channel name (with starting #)
 *   - [bot_name]: defaults to Monolog
 *   - [icon_emoji]: defaults to null
 *   - [use_attachment]: bool, defaults to true
 *   - [use_short_attachment]: bool, defaults to false
 *   - [include_extra]: bool, defaults to false
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [timeout]: float
 *   - [connection_timeout]: float
 *
 * - slackwebhook:
 *   - webhook_url: slack webhook URL
 *   - channel: channel name (with starting #)
 *   - [bot_name]: defaults to Monolog
 *   - [icon_emoji]: defaults to null
 *   - [use_attachment]: bool, defaults to true
 *   - [use_short_attachment]: bool, defaults to false
 *   - [include_extra]: bool, defaults to false
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - slackbot:
 *   - team: slack team slug
 *   - token: slackbot token
 *   - channel: channel name (with starting #)
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
 * - loggly:
 *   - token: loggly api token
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [tags]: tag names
 *
 * - logentries:
 *   - token: logentries api token
 *   - [use_ssl]: whether or not SSL encryption should be used, defaults to true
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [timeout]: float
 *   - [connection_timeout]: float
 *
 * - insightops:
 *   - token: Log token supplied by InsightOps
 *   - region: Region where InsightOps account is hosted. Could be 'us' or 'eu'. Defaults to 'us'
 *   - [use_ssl]: whether or not SSL encryption should be used, defaults to true
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - flowdock:
 *   - token: flowdock api token
 *   - source: human readable identifier of the application
 *   - from_email: email address of the message sender
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - rollbar:
 *   - id: RollbarNotifier service (mandatory if token is not provided)
 *   - token: rollbar api token (skip if you provide a RollbarNotifier service id)
 *   - [config]: config values from https://github.com/rollbar/rollbar-php#configuration-reference
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - server_log:
 *   - host: server log host. ex: 127.0.0.1:9911
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *
 * - telegram:
 *   - token: Telegram bot access token provided by BotFather
 *   - channel: Telegram channel name
 *   - [level]: level name or int value, defaults to DEBUG
 *   - [bubble]: bool, defaults to true
 *   - [parse_mode]: optional the kind of formatting that is used for the message
 *   - [disable_webpage_preview]: bool, defaults to false, disables link previews for links in the message
 *   - [disable_notification]: bool, defaults to false, sends the message silently. Users will receive a notification with no sound
 *   - [split_long_messages]: bool, defaults to false, split messages longer than 4096 bytes into multiple messages
 *   - [delay_between_messages]: bool, defaults to false, adds a 1sec delay/sleep between sending split messages
 *   - [topic]: int, defaults to null, message_thread_id, unique identifier for the target message thread (topic) of the forum; for forum supergroups only
 *
 * - sampling:
 *   - handler: the wrapped handler's name
 *   - factor: the sampling factor (e.g. 10 means every ~10th record is sampled)
 *
 * All handlers can also be marked with `nested: true` to make sure they are never added explicitly to the stack
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 *
 * @final since 3.9.0
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('monolog');
        $rootNode = $treeBuilder->getRootNode();

        $handlers = $rootNode
            ->fixXmlConfig('channel')
            ->fixXmlConfig('handler')
            ->children()
                ->scalarNode('use_microseconds')->defaultTrue()->end()
                ->arrayNode('channels')
                    ->canBeUnset()
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('handlers');

        $handlers
            ->canBeUnset()
            ->useAttributeAsKey('name')
            ->validate()
                ->ifTrue(function ($v) { return isset($v['debug']); })
                ->thenInvalid('The "debug" name cannot be used as it is reserved for the handler of the profiler')
            ->end()
            ->example([
                'syslog' => [
                    'type' => 'stream',
                    'path' => '/var/log/symfony.log',
                    'level' => 'ERROR',
                    'bubble' => 'false',
                    'formatter' => 'my_formatter',
                ],
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'WARNING',
                    'buffer_size' => 30,
                    'handler' => 'custom',
                ],
                'custom' => [
                    'type' => 'service',
                    'id' => 'my_handler',
                ],
            ]);

        $handlerNode = $handlers
            ->prototype('array')
                ->fixXmlConfig('member')
                ->fixXmlConfig('excluded_404')
                ->fixXmlConfig('excluded_http_code')
                ->fixXmlConfig('tag')
                ->fixXmlConfig('accepted_level')
                ->fixXmlConfig('header')
                ->canBeUnset();

        $handlerNode
            ->children()
                ->scalarNode('type')
                    ->isRequired()
                    ->treatNullLike('null')
                    ->beforeNormalization()
                        ->always()
                        ->then(function ($v) { return strtolower($v); })
                    ->end()
                ->end()
                ->scalarNode('id')->end() // service & rollbar
                ->scalarNode('priority')->defaultValue(0)->end()
                ->scalarNode('level')->defaultValue('DEBUG')->end()
                ->booleanNode('bubble')->defaultTrue()->end()
                ->scalarNode('app_name')->defaultNull()->end()
                ->booleanNode('fill_extra_context')->defaultFalse()->end() // sentry
                ->booleanNode('include_stacktraces')->defaultFalse()->end()
                ->arrayNode('process_psr_3_messages')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifTrue(static function ($v) { return !\is_array($v); })
                        ->then(static function ($v) { return ['enabled' => $v]; })
                    ->end()
                    ->children()
                        ->booleanNode('enabled')->defaultNull()->end()
                        ->scalarNode('date_format')->end()
                        ->booleanNode('remove_used_context_fields')->end()
                    ->end()
                ->end()
                ->scalarNode('path')->defaultValue('%kernel.logs_dir%/%kernel.environment%.log')->end() // stream and rotating
                ->scalarNode('file_permission')  // stream and rotating
                    ->defaultNull()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function ($v) {
                            if ('0' === substr($v, 0, 1)) {
                                return octdec($v);
                            }

                            return (int) $v;
                        })
                    ->end()
                ->end()
                ->booleanNode('use_locking')->defaultFalse()->end() // stream and rotating
                ->scalarNode('filename_format')->defaultValue('{filename}-{date}')->end() // rotating
                ->scalarNode('date_format')->defaultValue('Y-m-d')->end() // rotating
                ->scalarNode('ident')->defaultFalse()->end() // syslog and syslogudp
                ->scalarNode('logopts')->defaultValue(\LOG_PID)->end() // syslog
                ->scalarNode('facility')->defaultValue('user')->end() // syslog
                ->scalarNode('max_files')->defaultValue(0)->end() // rotating
                ->scalarNode('action_level')->defaultValue('WARNING')->end() // fingers_crossed
                ->scalarNode('activation_strategy')->defaultNull()->end() // fingers_crossed
                ->booleanNode('stop_buffering')->defaultTrue()->end()// fingers_crossed
                ->scalarNode('passthru_level')->defaultNull()->end() // fingers_crossed
                ->arrayNode('excluded_404s') // fingers_crossed
                    ->canBeUnset()
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('excluded_http_codes') // fingers_crossed
                    ->canBeUnset()
                    ->beforeNormalization()
                        ->always(function ($values) {
                            return array_map(function ($value) {
                                /*
                                 * Allows YAML:
                                 *   excluded_http_codes: [403, 404, { 400: ['^/foo', '^/bar'] }]
                                 *
                                 * and XML:
                                 *   <monolog:excluded-http-code code="403">
                                 *     <monolog:url>^/foo</monolog:url>
                                 *     <monolog:url>^/bar</monolog:url>
                                 *   </monolog:excluded-http-code>
                                 *   <monolog:excluded-http-code code="404" />
                                 */

                                if (\is_array($value)) {
                                    return isset($value['code']) ? $value : ['code' => key($value), 'urls' => current($value)];
                                }

                                return ['code' => $value, 'urls' => []];
                            }, $values);
                        })
                    ->end()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('code')->end()
                            ->arrayNode('urls')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('accepted_levels') // filter
                    ->canBeUnset()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('min_level')->defaultValue('DEBUG')->end() // filter
                ->scalarNode('max_level')->defaultValue('EMERGENCY')->end() // filter
                ->scalarNode('buffer_size')->defaultValue(0)->end() // fingers_crossed and buffer
                ->booleanNode('flush_on_overflow')->defaultFalse()->end() // buffer
                ->scalarNode('handler')->end() // fingers_crossed and buffer
                ->scalarNode('url')->end() // cube
                ->scalarNode('exchange')->end() // amqp
                ->scalarNode('exchange_name')->defaultValue('log')->end() // amqp
                ->scalarNode('room')->end() // hipchat
                ->scalarNode('message_format')->defaultValue('text')->end() // hipchat
                ->scalarNode('api_version')->defaultNull()->end() // hipchat
                ->scalarNode('channel')->defaultNull()->end() // slack & slackwebhook & slackbot & telegram
                ->scalarNode('bot_name')->defaultValue('Monolog')->end() // slack & slackwebhook
                ->scalarNode('use_attachment')->defaultTrue()->end() // slack & slackwebhook
                ->scalarNode('use_short_attachment')->defaultFalse()->end() // slack & slackwebhook
                ->scalarNode('include_extra')->defaultFalse()->end() // slack & slackwebhook
                ->scalarNode('icon_emoji')->defaultNull()->end() // slack & slackwebhook
                ->scalarNode('webhook_url')->end() // slackwebhook
                ->scalarNode('team')->end() // slackbot
                ->scalarNode('notify')->defaultFalse()->end() // hipchat
                ->scalarNode('nickname')->defaultValue('Monolog')->end() // hipchat
                ->scalarNode('token')->end() // pushover & hipchat & loggly & logentries & flowdock & rollbar & slack & slackbot & insightops & telegram
                ->scalarNode('region')->end() // insightops
                ->scalarNode('source')->end() // flowdock
                ->booleanNode('use_ssl')->defaultTrue()->end() // logentries & hipchat & insightops
                ->variableNode('user') // pushover
                    ->validate()
                        ->ifTrue(function ($v) {
                            return !\is_string($v) && !\is_array($v);
                        })
                        ->thenInvalid('User must be a string or an array.')
                    ->end()
                ->end()
                ->scalarNode('title')->defaultNull()->end() // pushover
                ->scalarNode('host')->defaultNull()->end() // syslogudp & hipchat
                ->scalarNode('port')->defaultValue(514)->end() // syslogudp
                ->arrayNode('config')
                    ->canBeUnset()
                    ->prototype('scalar')->end()
                ->end() // rollbar
                ->arrayNode('members') // group, whatfailuregroup, fallbackgroup
                    ->canBeUnset()
                    ->performNoDeepMerging()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('connection_string')->end() // socket_handler
                ->scalarNode('timeout')->end() // socket_handler, logentries, pushover, hipchat & slack
                ->scalarNode('time')->defaultValue(60)->end() // deduplication
                ->scalarNode('deduplication_level')->defaultValue(Logger::ERROR)->end() // deduplication
                ->scalarNode('store')->defaultNull()->end() // deduplication
                ->scalarNode('connection_timeout')->end() // socket_handler, logentries, pushover, hipchat & slack
                ->booleanNode('persistent')->end() // socket_handler
                ->scalarNode('dsn')->end() // raven_handler, sentry_handler
                ->scalarNode('hub_id')->defaultNull()->end() // sentry_handler
                ->scalarNode('client_id')->defaultNull()->end() // raven_handler, sentry_handler
                ->scalarNode('auto_log_stacks')->defaultFalse()->end() // raven_handler
                ->scalarNode('release')->defaultNull()->end() // raven_handler, sentry_handler
                ->scalarNode('environment')->defaultNull()->end() // raven_handler, sentry_handler
                ->scalarNode('message_type')->defaultValue(0)->end() // error_log
                ->scalarNode('parse_mode')->defaultNull()->end() // telegram
                ->booleanNode('disable_webpage_preview')->defaultNull()->end() // telegram
                ->booleanNode('disable_notification')->defaultNull()->end() // telegram
                ->booleanNode('split_long_messages')->defaultFalse()->end() // telegram
                ->booleanNode('delay_between_messages')->defaultFalse()->end() // telegram
                ->scalarNode('topic')->defaultNull()->end() // telegram
                ->integerNode('factor')->defaultValue(1)->min(1)->end() // sampling
                ->arrayNode('tags') // loggly
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function ($v) { return explode(',', $v); })
                    ->end()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(function ($v) { return array_filter(array_map('trim', $v)); })
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
                 // console
                ->variableNode('console_formater_options')
                    ->setDeprecated('symfony/monolog-bundle', 3.7, '"%path%.%node%" is deprecated, use "%path%.console_formatter_options" instead.')
                    ->validate()
                        ->ifTrue(function ($v) {
                            return !\is_array($v);
                        })
                        ->thenInvalid('The console_formater_options must be an array.')
                    ->end()
                ->end()
                ->variableNode('console_formatter_options')
                    ->defaultValue([])
                    ->validate()
                        ->ifTrue(static function ($v) { return !\is_array($v); })
                        ->thenInvalid('The console_formatter_options must be an array.')
                    ->end()
                ->end()
                ->scalarNode('formatter')->end()
                ->booleanNode('nested')->defaultFalse()->end()
            ->end();

        $this->addGelfSection($handlerNode);
        $this->addMongoSection($handlerNode);
        $this->addElasticsearchSection($handlerNode);
        $this->addRedisSection($handlerNode);
        $this->addPredisSection($handlerNode);
        $this->addMailerSection($handlerNode);
        $this->addVerbosityLevelSection($handlerNode);
        $this->addChannelsSection($handlerNode);

        $handlerNode
            ->beforeNormalization()
                ->always(static function ($v) {
                    if (empty($v['console_formatter_options']) && !empty($v['console_formater_options'])) {
                        $v['console_formatter_options'] = $v['console_formater_options'];
                    }

                    return $v;
                })
            ->end()
            ->validate()
                ->always(static function ($v) {
                    unset($v['console_formater_options']);

                    return $v;
                })
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'service' === $v['type'] && !empty($v['formatter']); })
                ->thenInvalid('Service handlers can not have a formatter configured in the bundle, you must reconfigure the service itself instead')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return ('fingers_crossed' === $v['type'] || 'buffer' === $v['type'] || 'filter' === $v['type'] || 'sampling' === $v['type']) && empty($v['handler']); })
                ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler or BufferHandler or FilterHandler or SamplingHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'fingers_crossed' === $v['type'] && !empty($v['excluded_404s']) && !empty($v['activation_strategy']); })
                ->thenInvalid('You can not use excluded_404s together with a custom activation_strategy in a FingersCrossedHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'fingers_crossed' === $v['type'] && !empty($v['excluded_http_codes']) && !empty($v['activation_strategy']); })
                ->thenInvalid('You can not use excluded_http_codes together with a custom activation_strategy in a FingersCrossedHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'fingers_crossed' === $v['type'] && !empty($v['excluded_http_codes']) && !empty($v['excluded_404s']); })
                ->thenInvalid('You can not use excluded_http_codes together with excluded_404s in a FingersCrossedHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'fingers_crossed' !== $v['type'] && (!empty($v['excluded_http_codes']) || !empty($v['excluded_404s'])); })
                ->thenInvalid('You can only use excluded_http_codes/excluded_404s with a FingersCrossedHandler definition')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'filter' === $v['type'] && 'DEBUG' !== $v['min_level'] && !empty($v['accepted_levels']); })
                ->thenInvalid('You can not use min_level together with accepted_levels in a FilterHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'filter' === $v['type'] && 'EMERGENCY' !== $v['max_level'] && !empty($v['accepted_levels']); })
                ->thenInvalid('You can not use max_level together with accepted_levels in a FilterHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'rollbar' === $v['type'] && !empty($v['id']) && !empty($v['token']); })
                ->thenInvalid('You can not use both an id and a token in a RollbarHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'rollbar' === $v['type'] && empty($v['id']) && empty($v['token']); })
                ->thenInvalid('The id or the token has to be specified to use a RollbarHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'telegram' === $v['type'] && (empty($v['token']) || empty($v['channel'])); })
                ->thenInvalid('The token and channel have to be specified to use a TelegramBotHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'service' === $v['type'] && !isset($v['id']); })
                ->thenInvalid('The id has to be specified to use a service as handler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'syslogudp' === $v['type'] && !isset($v['host']); })
                ->thenInvalid('The host has to be specified to use a syslogudp as handler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'socket' === $v['type'] && !isset($v['connection_string']); })
                ->thenInvalid('The connection_string has to be specified to use a SocketHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'pushover' === $v['type'] && (empty($v['token']) || empty($v['user'])); })
                ->thenInvalid('The token and user have to be specified to use a PushoverHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'raven' === $v['type'] && !\array_key_exists('dsn', $v) && null === $v['client_id']; })
                ->thenInvalid('The DSN has to be specified to use a RavenHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'sentry' === $v['type'] && !\array_key_exists('dsn', $v) && null === $v['hub_id'] && null === $v['client_id']; })
                ->thenInvalid('The DSN has to be specified to use Sentry\'s handler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'sentry' === $v['type'] && null !== $v['hub_id'] && null !== $v['client_id']; })
                ->thenInvalid('You can not use both a hub_id and a client_id in a Sentry handler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'hipchat' === $v['type'] && (empty($v['token']) || empty($v['room'])); })
                ->thenInvalid('The token and room have to be specified to use a HipChatHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'hipchat' === $v['type'] && !\in_array($v['message_format'], ['text', 'html']); })
                ->thenInvalid('The message_format has to be "text" or "html" in a HipChatHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'hipchat' === $v['type'] && null !== $v['api_version'] && !\in_array($v['api_version'], ['v1', 'v2'], true); })
                ->thenInvalid('The api_version has to be "v1" or "v2" in a HipChatHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'slack' === $v['type'] && (empty($v['token']) || empty($v['channel'])); })
                ->thenInvalid('The token and channel have to be specified to use a SlackHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'slackwebhook' === $v['type'] && (empty($v['webhook_url'])); })
                ->thenInvalid('The webhook_url have to be specified to use a SlackWebhookHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'slackbot' === $v['type'] && (empty($v['team']) || empty($v['token']) || empty($v['channel'])); })
                ->thenInvalid('The team, token and channel have to be specified to use a SlackbotHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'cube' === $v['type'] && empty($v['url']); })
                ->thenInvalid('The url has to be specified to use a CubeHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'amqp' === $v['type'] && empty($v['exchange']); })
                ->thenInvalid('The exchange has to be specified to use a AmqpHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'loggly' === $v['type'] && empty($v['token']); })
                ->thenInvalid('The token has to be specified to use a LogglyHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'loggly' === $v['type'] && !empty($v['tags']); })
                ->then(function ($v) {
                    $invalidTags = preg_grep('/^[a-z0-9][a-z0-9\.\-_]*$/i', $v['tags'], \PREG_GREP_INVERT);
                    if (!empty($invalidTags)) {
                        throw new InvalidConfigurationException(\sprintf('The following Loggly tags are invalid: %s.', implode(', ', $invalidTags)));
                    }

                    return $v;
                })
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'logentries' === $v['type'] && empty($v['token']); })
                ->thenInvalid('The token has to be specified to use a LogEntriesHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'insightops' === $v['type'] && empty($v['token']); })
                ->thenInvalid('The token has to be specified to use a InsightOpsHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'flowdock' === $v['type'] && empty($v['token']); })
                ->thenInvalid('The token has to be specified to use a FlowdockHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'flowdock' === $v['type'] && empty($v['from_email']); })
                ->thenInvalid('The from_email has to be specified to use a FlowdockHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'flowdock' === $v['type'] && empty($v['source']); })
                ->thenInvalid('The source has to be specified to use a FlowdockHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'server_log' === $v['type'] && empty($v['host']); })
                ->thenInvalid('The host has to be specified to use a ServerLogHandler')
            ->end()
        ;

        return $treeBuilder;
    }

    private function addGelfSection(ArrayNodeDefinition $handerNode)
    {
        $handerNode
            ->children()
                ->arrayNode('publisher')
                    ->canBeUnset()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function ($v) { return ['id' => $v]; })
                    ->end()
                    ->children()
                        ->scalarNode('id')->end()
                        ->scalarNode('hostname')->end()
                        ->scalarNode('port')->defaultValue(12201)->end()
                        ->scalarNode('chunk_size')->defaultValue(1420)->end()
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return !isset($v['id']) && !isset($v['hostname']);
                        })
                        ->thenInvalid('What must be set is either the hostname or the id.')
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'gelf' === $v['type'] && !isset($v['publisher']); })
                ->thenInvalid('The publisher has to be specified to use a GelfHandler')
            ->end()
        ;
    }

    private function addMongoSection(ArrayNodeDefinition $handerNode)
    {
        $handerNode
            ->children()
                ->arrayNode('mongo')
                    ->canBeUnset()
                    ->beforeNormalization()
                    ->ifString()
                    ->then(function ($v) { return ['id' => $v]; })
                    ->end()
                    ->children()
                        ->scalarNode('id')->end()
                        ->scalarNode('host')->end()
                        ->scalarNode('port')->defaultValue(27017)->end()
                        ->scalarNode('user')->end()
                        ->scalarNode('pass')->end()
                        ->scalarNode('database')->defaultValue('monolog')->end()
                        ->scalarNode('collection')->defaultValue('logs')->end()
                    ->end()
                    ->validate()
                    ->ifTrue(function ($v) {
                        return !isset($v['id']) && !isset($v['host']);
                    })
                    ->thenInvalid('What must be set is either the host or the id.')
                    ->end()
                    ->validate()
                    ->ifTrue(function ($v) {
                        return isset($v['user']) && !isset($v['pass']);
                    })
                    ->thenInvalid('If you set user, you must provide a password.')
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'mongo' === $v['type'] && !isset($v['mongo']); })
                ->thenInvalid('The mongo configuration has to be specified to use a MongoHandler')
            ->end()
        ;
    }

    private function addElasticsearchSection(ArrayNodeDefinition $handerNode)
    {
        $handerNode
            ->children()
                ->arrayNode('elasticsearch')
                    ->canBeUnset()
                    ->beforeNormalization()
                    ->ifString()
                    ->then(function ($v) { return ['id' => $v]; })
                    ->end()
                    ->children()
                        ->scalarNode('id')->end()
                        ->scalarNode('host')->end()
                        ->scalarNode('port')->defaultValue(9200)->end()
                        ->scalarNode('transport')->defaultValue('Http')->end()
                        ->scalarNode('user')->defaultNull()->end()
                        ->scalarNode('password')->defaultNull()->end()
                    ->end()
                    ->validate()
                    ->ifTrue(function ($v) {
                        return !isset($v['id']) && !isset($v['host']);
                    })
                    ->thenInvalid('What must be set is either the host or the id.')
                    ->end()
                ->end()
                ->scalarNode('index')->defaultValue('monolog')->end() // elasticsearch & elastic_search & elastica
                ->scalarNode('document_type')->defaultValue('logs')->end() // elasticsearch & elastic_search & elastica
                ->scalarNode('ignore_error')->defaultValue(false)->end() // elasticsearch & elastic_search & elastica
            ->end()
        ;
    }

    private function addRedisSection(ArrayNodeDefinition $handerNode)
    {
        $handerNode
            ->children()
                ->arrayNode('redis')
                    ->canBeUnset()
                    ->beforeNormalization()
                    ->ifString()
                    ->then(function ($v) { return ['id' => $v]; })
                    ->end()
                    ->children()
                        ->scalarNode('id')->end()
                        ->scalarNode('host')->end()
                        ->scalarNode('password')->defaultNull()->end()
                        ->scalarNode('port')->defaultValue(6379)->end()
                        ->scalarNode('database')->defaultValue(0)->end()
                        ->scalarNode('key_name')->defaultValue('monolog_redis')->end()
                    ->end()
                    ->validate()
                    ->ifTrue(function ($v) {
                        return !isset($v['id']) && !isset($v['host']);
                    })
                    ->thenInvalid('What must be set is either the host or the service id of the Redis client.')
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'redis' === $v['type'] && empty($v['redis']); })
                ->thenInvalid('The host has to be specified to use a RedisLogHandler')
            ->end()
        ;
    }

    private function addPredisSection(ArrayNodeDefinition $handerNode)
    {
        $handerNode
            ->children()
                ->arrayNode('predis')
                    ->canBeUnset()
                    ->beforeNormalization()
                    ->ifString()
                    ->then(function ($v) { return ['id' => $v]; })
                    ->end()
                    ->children()
                        ->scalarNode('id')->end()
                        ->scalarNode('host')->end()
                    ->end()
                    ->validate()
                    ->ifTrue(function ($v) {
                        return !isset($v['id']) && !isset($v['host']);
                    })
                    ->thenInvalid('What must be set is either the host or the service id of the Predis client.')
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'predis' === $v['type'] && empty($v['redis']); })
                ->thenInvalid('The host has to be specified to use a RedisLogHandler')
            ->end()
        ;
    }

    private function addMailerSection(ArrayNodeDefinition $handerNode)
    {
        $handerNode
            ->children()
                ->scalarNode('from_email')->end() // swift_mailer, native_mailer, symfony_mailer and flowdock
                ->arrayNode('to_email') // swift_mailer, native_mailer and symfony_mailer
                    ->prototype('scalar')->end()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function ($v) { return [$v]; })
                    ->end()
                ->end()
                ->scalarNode('subject')->end() // swift_mailer, native_mailer and symfony_mailer
                ->scalarNode('content_type')->defaultNull()->end() // swift_mailer and symfony_mailer
                ->arrayNode('headers') // native_mailer
                    ->canBeUnset()
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('mailer')->defaultNull()->end() // swift_mailer and symfony_mailer
                ->arrayNode('email_prototype') // swift_mailer and symfony_mailer
                    ->canBeUnset()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function ($v) { return ['id' => $v]; })
                    ->end()
                    ->children()
                        ->scalarNode('id')->isRequired()->end()
                        ->scalarNode('method')->defaultNull()->end()
                    ->end()
                ->end()
                ->booleanNode('lazy')->defaultValue(true)->end() // swift_mailer
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'swift_mailer' === $v['type'] && empty($v['email_prototype']) && (empty($v['from_email']) || empty($v['to_email']) || empty($v['subject'])); })
                ->thenInvalid('The sender, recipient and subject or an email prototype have to be specified to use a SwiftMailerHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'native_mailer' === $v['type'] && (empty($v['from_email']) || empty($v['to_email']) || empty($v['subject'])); })
                ->thenInvalid('The sender, recipient and subject have to be specified to use a NativeMailerHandler')
            ->end()
            ->validate()
                ->ifTrue(function ($v) { return 'symfony_mailer' === $v['type'] && empty($v['email_prototype']) && (empty($v['from_email']) || empty($v['to_email']) || empty($v['subject'])); })
                ->thenInvalid('The sender, recipient and subject or an email prototype have to be specified to use the Symfony MailerHandler')
            ->end()
        ;
    }

    private function addVerbosityLevelSection(ArrayNodeDefinition $handerNode)
    {
        $handerNode
            ->children()
                ->arrayNode('verbosity_levels') // console
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(function ($v) {
                            $map = [];
                            $verbosities = ['VERBOSITY_QUIET', 'VERBOSITY_NORMAL', 'VERBOSITY_VERBOSE', 'VERBOSITY_VERY_VERBOSE', 'VERBOSITY_DEBUG'];
                            // allow numeric indexed array with ascendning verbosity and lowercase names of the constants
                            foreach ($v as $verbosity => $level) {
                                if (\is_int($verbosity) && isset($verbosities[$verbosity])) {
                                    $map[$verbosities[$verbosity]] = strtoupper($level);
                                } else {
                                    $map[strtoupper($verbosity)] = strtoupper($level);
                                }
                            }

                            return $map;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('VERBOSITY_QUIET')->defaultValue('ERROR')->end()
                        ->scalarNode('VERBOSITY_NORMAL')->defaultValue('WARNING')->end()
                        ->scalarNode('VERBOSITY_VERBOSE')->defaultValue('NOTICE')->end()
                        ->scalarNode('VERBOSITY_VERY_VERBOSE')->defaultValue('INFO')->end()
                        ->scalarNode('VERBOSITY_DEBUG')->defaultValue('DEBUG')->end()
                    ->end()
                    ->validate()
                        ->always(function ($v) {
                            $map = [];
                            foreach ($v as $verbosity => $level) {
                                $verbosityConstant = 'Symfony\Component\Console\Output\OutputInterface::'.$verbosity;

                                if (!\defined($verbosityConstant)) {
                                    throw new InvalidConfigurationException(\sprintf('The configured verbosity "%s" is invalid as it is not defined in Symfony\Component\Console\Output\OutputInterface.', $verbosity));
                                }

                                try {
                                    if (Logger::API === 3) {
                                        $level = Logger::toMonologLevel($level)->value;
                                    } else {
                                        $level = Logger::toMonologLevel(is_numeric($level) ? (int) $level : $level);
                                    }
                                } catch (\Psr\Log\InvalidArgumentException $e) {
                                    throw new InvalidConfigurationException(\sprintf('The configured minimum log level "%s" for verbosity "%s" is invalid as it is not defined in Monolog\Logger.', $level, $verbosity));
                                }

                                $map[\constant($verbosityConstant)] = $level;
                            }

                            return $map;
                        })
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addChannelsSection(ArrayNodeDefinition $handerNode)
    {
        $handerNode
            ->children()
                ->arrayNode('channels')
                    ->fixXmlConfig('channel', 'elements')
                    ->canBeUnset()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function ($v) { return ['elements' => [$v]]; })
                    ->end()
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return \is_array($v) && is_numeric(key($v)); })
                        ->then(function ($v) { return ['elements' => $v]; })
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) { return empty($v); })
                        ->thenUnset()
                    ->end()
                    ->validate()
                        ->always(function ($v) {
                            $isExclusive = null;
                            if (isset($v['type'])) {
                                $isExclusive = 'exclusive' === $v['type'];
                            }

                            $elements = [];
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

                            if (!\count($elements)) {
                                return null;
                            }

                            // de-duplicating $elements here in case the handlers are redefined, see https://github.com/symfony/monolog-bundle/issues/433
                            return ['type' => $isExclusive ? 'exclusive' : 'inclusive', 'elements' => array_unique($elements)];
                        })
                    ->end()
                    ->children()
                        ->scalarNode('type')
                            ->validate()
                                ->ifNotInArray(['inclusive', 'exclusive'])
                                ->thenInvalid('The type of channels has to be inclusive or exclusive')
                            ->end()
                        ->end()
                        ->arrayNode('elements')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
