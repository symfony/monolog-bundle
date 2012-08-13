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

use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\LoggerChannelPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MonologExtensionTest extends DependencyInjectionTest
{
    public function testLoadWithDefault()
    {
        $container = $this->getContainer(array(array('handlers' => array('main' => array('type' => 'stream')))));

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('%kernel.logs_dir%/%kernel.environment%.log', \Monolog\Logger::DEBUG, true));
    }

    public function testLoadWithCustomValues()
    {
        $container = $this->getContainer(array(array('handlers' => array('custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR')))));
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::ERROR, false));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testExceptionWhenInvalidHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'invalid_handler')))), $container);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingFingerscrossedWithoutHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'fingers_crossed')))), $container);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingBufferWithoutHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'buffer')))), $container);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingGelfWithoutPublisher()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('gelf' => array('type' => 'gelf')))), $container);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingGelfWithoutPublisherHostname()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('gelf' => array('type' => 'gelf', 'publisher' => array())))), $container);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingServiceWithoutId()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'service')))), $container);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingDebugName()
    {
        // logger
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('debug' => array('type' => 'stream')))), $container);
    }

    protected function getContainer(array $config = array())
    {
        $container = new ContainerBuilder();
        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->addCompilerPass(new LoggerChannelPass());

        $loader = new MonologExtension();
        $loader->load($config, $container);
        $container->compile();

        return $container;
    }
}
