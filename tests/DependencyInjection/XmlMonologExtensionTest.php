<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * XML configuration deprecated since Symfony 7.4.
 */
#[Group('legacy')]
class XmlMonologExtensionTest extends FixtureMonologExtensionTestCase
{
    protected function loadFixture(ContainerBuilder $container, string $fixture)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/xml'));
        $loader->load($fixture.'.xml');
    }
}
