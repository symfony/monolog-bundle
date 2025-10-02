<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('security_logger', 'Foo')
        ->tag('monolog.logger', ['channel' => 'security']);

    $services->set('doctrine_logger', 'Foo')
        ->tag('monolog.logger', ['channel' => 'doctrine']);

    $services->set('foo_logger', 'Foo')
        ->tag('monolog.logger', ['channel' => 'foo']);

    $services->set('bar_logger', 'Foo')
        ->tag('monolog.logger', ['channel' => 'bar']);

    $containerConfigurator->extension('monolog', [
        'handlers' => [
            'custom' => [
                'type' => 'stream',
                'path' => '/tmp/symfony.log',
                'bubble' => false,
                'level' => 'ERROR',
                'channels' => 'foo',
            ],
            'main' => [
                'type' => 'group',
                'members' => [
                    'nested',
                ],
                'channels' => [
                    '!foo',
                    '!bar',
                ],
            ],
            'nested' => [
                'type' => 'stream',
            ],
            'extra' => [
                'type' => 'syslog',
                'ident' => 'monolog',
                'facility' => 'user',
                'level' => 'ALERT',
            ],
            'more' => [
                'type' => 'native_mailer',
                'to_email' => 'monitoring@example.org',
                'from_email' => 'webmaster@example.org',
                'subject' => 'Monolog report',
                'level' => 'CRITICAL',
                'channels' => [
                    'type' => 'inclusive',
                    'elements' => [
                        'security',
                        'doctrine',
                    ],
                ],
            ],
        ],
    ]);
};
