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
    $containerConfigurator->import(__DIR__.'/new_and_priority_import.php');

    $containerConfigurator->extension('monolog', [
        'handlers' => [
            'custom' => [
                'type' => 'stream',
                'path' => '/tmp/symfony.log',
                'bubble' => true,
                'level' => 'WARNING',
            ],
            'first' => [
                'type' => 'rotating_file',
                'path' => '/tmp/monolog.log',
                'bubble' => true,
                'level' => 'ERROR',
                'priority' => 3,
            ],
            'last' => [
                'type' => 'stream',
                'path' => '/tmp/last.log',
                'bubble' => true,
                'level' => 'ERROR',
                'priority' => -3,
            ],
        ],
    ]);
};
