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
        ->bubble(true)
        ->level('ERROR');

    $monologConfig
        ->handler('main')
        ->type('buffer')
        ->level('INFO')
        ->handler('nested');

    $monologConfig
        ->handler('nested')
        ->type('stream');
};
