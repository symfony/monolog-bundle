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
        ->handler('server_log')
        ->type('server_log')
        ->host('0:9911');
};
