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
    $containerConfigurator->import(__DIR__.'/new_at_end_import.php');

    $containerConfigurator->extension('monolog', [
        'handlers' => [
            'custom' => [
                'type' => 'stream',
                'path' => '/tmp/symfony.log',
                'bubble' => false,
                'level' => 'WARNING',
            ],
            'new' => [
                'type' => 'stream',
                'path' => '/tmp/monolog.log',
                'bubble' => true,
                'level' => 'ERROR',
            ],
        ],
    ]);
};
