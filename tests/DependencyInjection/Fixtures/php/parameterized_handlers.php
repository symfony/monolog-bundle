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
    $parameters = $containerConfigurator->parameters();

    $parameters->set('channel_parameter', 'some_channel');

    $services = $containerConfigurator->services();

    $services->set('foo_logger', 'Foo')
        ->tag('monolog.logger', ['channel' => '%channel_parameter%']);

    $containerConfigurator->extension('monolog', [
        'handlers' => [
            'custom' => [
                'type' => 'stream',
                'path' => '/tmp/symfony.log',
                'channels' => '%channel_parameter%',
            ],
        ],
    ]);
};
