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
        ->handler('mailer')
        ->type('native_mailer')
        ->fromEmail('foo@example.com')
        ->toEmail('bar@exemple.com')
        ->subject('a subject')
        ->headers(['Foo: bar', 'Baz: inga']);
};
