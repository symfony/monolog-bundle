<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return static function (Symfony\Config\MonologConfig $monologConfig): void {
    $monologConfig
        ->handler('custom')
        ->type('stream')
        ->path('/tmp/symfony.log')
        ->bubble(false)
        ->level('ERROR')
        ->filePermission(438);

    $monologConfig
        ->handler('main')
        ->type('fingers_crossed')
        ->actionLevel('ERROR')
        ->passthruLevel('NOTICE')
        ->handler('nested');

    $monologConfig
        ->handler('nested')
        ->type('stream');

    $monologConfig
        ->handler('filtered')
        ->type('filter')
        ->acceptedLevels(['WARNING', 'ERROR'])
        ->handler('nested2');

    $monologConfig
        ->handler('nested2')
        ->type('stream');
};
