<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Monolog\Formatter\ChromePHPFormatter;
use Monolog\Formatter\GelfMessageFormatter;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogglyFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Formatter\ScalarFormatter;
use Monolog\Formatter\SyslogFormatter;
use Monolog\Formatter\WildfireFormatter;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->alias('logger', 'monolog.logger')
        ->alias(LoggerInterface::class, 'logger')

        ->set('monolog.logger')
            ->parent('monolog.logger_prototype')
            ->args(['index_0' => 'app'])
            ->call('useMicrosecondTimestamps', [param('monolog.use_microseconds')])
            ->tag('monolog.channel_logger')

        ->set('monolog.logger_prototype', Logger::class)
            ->args([abstract_arg('channel')])
            ->abstract()

        // Formatters
        ->set('monolog.formatter.chrome_php', ChromePHPFormatter::class)
        ->set('monolog.formatter.gelf_message', GelfMessageFormatter::class)
        ->set('monolog.formatter.html', HtmlFormatter::class)
        ->set('monolog.formatter.json', JsonFormatter::class)
        ->set('monolog.formatter.line', LineFormatter::class)
        ->set('monolog.formatter.syslog', SyslogFormatter::class)
        ->set('monolog.formatter.loggly', LogglyFormatter::class)
        ->set('monolog.formatter.normalizer', NormalizerFormatter::class)
        ->set('monolog.formatter.scalar', ScalarFormatter::class)
        ->set('monolog.formatter.wildfire', WildfireFormatter::class)

        ->set('monolog.formatter.logstash', LogstashFormatter::class)
            ->args(['app'])

        ->set('monolog.http_client', HttpClientInterface::class)
            ->factory([HttpClient::class, 'create'])
    ;
};
