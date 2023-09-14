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

#[AsMonologProcessor(handler: 'foo_handler')]
class FooProcessorWithPriority
{
    #[AsMonologProcessor(channel: 'ccc_channel', priority: '10')]
    public function __invoke(): void
    {
    }
}
