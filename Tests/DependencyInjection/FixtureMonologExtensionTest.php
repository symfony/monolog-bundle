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

abstract class FixtureMonologExtensionTest extends DependencyInjectionTest
{
    public function testLoadWithSeveralHandlers()
    {
        $container = $this->getContainer('multiple_handlers');

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertCount(3, $logger->getMethodCalls());
        $this->assertDICDefinitionMethodCallAt(2, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.filtered')));

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::ERROR, false));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.fingers_crossed.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('monolog.handler.nested'), \Monolog\Logger::ERROR, 0, true, true, \Monolog\Logger::NOTICE));

        $handler = $container->getDefinition('monolog.handler.filtered');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.filter.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('monolog.handler.nested2'), array(\Monolog\Logger::WARNING, \Monolog\Logger::ERROR), \Monolog\Logger::EMERGENCY, true));
    }

    public function testLoadWithOverwriting()
    {
        $container = $this->getContainer('overwriting');

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertCount(2, $logger->getMethodCalls());
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::WARNING, true));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.fingers_crossed.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('monolog.handler.nested'), \Monolog\Logger::ERROR, 0, true, true, null));
    }

    public function testLoadWithNewAtEnd()
    {
        $container = $this->getContainer('new_at_end');

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));
        $this->assertTrue($container->hasDefinition('monolog.handler.new'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertCount(3, $logger->getMethodCalls());
        $this->assertDICDefinitionMethodCallAt(2, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.new')));

        $handler = $container->getDefinition('monolog.handler.new');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/monolog.log', \Monolog\Logger::ERROR, true));
    }

    public function testLoadWithNewAndPriority()
    {
        $container = $this->getContainer('new_and_priority');

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));
        $this->assertTrue($container->hasDefinition('monolog.handler.first'));
        $this->assertTrue($container->hasDefinition('monolog.handler.last'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertCount(4, $logger->getMethodCalls());
        $this->assertDICDefinitionMethodCallAt(3, $logger, 'pushHandler', array(new Reference('monolog.handler.first')));
        $this->assertDICDefinitionMethodCallAt(2, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.last')));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.buffer.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('monolog.handler.nested'), 0, \Monolog\Logger::INFO, true));

        $handler = $container->getDefinition('monolog.handler.first');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.rotating_file.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/monolog.log', 0, \Monolog\Logger::ERROR, true));

        $handler = $container->getDefinition('monolog.handler.last');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/last.log', \Monolog\Logger::ERROR, true));
    }

    public function testHandlersWithChannels()
    {
        $container = $this->getContainer('handlers_with_channels');

        $this->assertEquals(
            array(
                'monolog.handler.custom' => array('type' => 'inclusive', 'elements' => array('foo')),
                'monolog.handler.main' => array('type' => 'exclusive', 'elements' => array('foo', 'bar')),
                'monolog.handler.extra' => null,
                'monolog.handler.more' => array('type' => 'inclusive', 'elements' => array('security', 'doctrine')),
            ),
            $container->getParameter('monolog.handlers_to_channels')
        );
    }

    protected function getContainer($fixture)
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new MonologExtension());

        $this->loadFixture($container, $fixture);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->addCompilerPass(new LoggerChannelPass());
        $container->compile();

        return $container;
    }

    abstract protected function loadFixture(ContainerBuilder $container, $fixture);
}
