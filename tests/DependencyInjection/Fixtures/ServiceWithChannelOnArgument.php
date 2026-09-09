<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Fixtures;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

class ServiceWithChannelOnArgument
{
    public function __construct(
        #[WithMonologChannel('fixture')]
        private readonly LoggerInterface $logger,
        #[WithMonologChannel('fixture_bis')]
        private readonly LoggerInterface $otherLogger,
        private readonly LoggerInterface $defaultLogger,
    ) {
    }
}
