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

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * XML configuration deprecated since Symfony 7.4.
 */
#[IgnoreDeprecations]
class XmlMonologExtensionTest extends FixtureMonologExtensionTestCase
{
    protected function loadFixture(ContainerBuilder $container, string $fixture)
    {
        if (!class_exists(XmlFileLoader::class)) {
            $this->markTestSkipped('The XML configuration has been removed in Symfony 8.0.');
        }

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/xml'));
        $loader->load($fixture.'.xml');
    }
}
