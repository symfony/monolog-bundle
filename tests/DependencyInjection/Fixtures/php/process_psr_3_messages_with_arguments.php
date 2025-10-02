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
        ->handler('with_arguments')
        ->type('stream')
        ->processPsr3Messages()
        ->dateFormat('Y');

    $monologConfig
        ->handler('without_arguments')
        ->type('stream')
        ->processPsr3Messages()
        ->enabled(true);
};
