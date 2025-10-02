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

use Symfony\Bundle\MonologBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class PhpMonologExtensionTest extends FixtureMonologExtensionTestCase
{
    protected function loadFixture(ContainerBuilder $container, $fixture)
    {
        $container->setDefinition('mailer', new Definition('Swiftmailer'));

        $generator = new ConfigBuilderGenerator(__DIR__.'/../../var/cache');
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures/php'), null, $generator);
        $loader->load($fixture.'.php');
    }
}
