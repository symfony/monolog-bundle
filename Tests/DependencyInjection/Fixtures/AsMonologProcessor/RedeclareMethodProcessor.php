<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures\AsMonologProcessor;

use Monolog\Attribute\AsMonologProcessor;

class RedeclareMethodProcessor
{
    #[AsMonologProcessor(method: 'foobar')]
    public function __invoke(): void
    {
    }
}