<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Compiler;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\AddProcessorsPass;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\LoggerChannelPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

class AddProcessorsPassTest extends TestCase
{
    public function testHandlerProcessors()
    {
        $container = $this->getContainer();

        $service = $container->getDefinition('monolog.handler.test');
        $calls = $service->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals(['pushProcessor', [new TypedReference('test', 'TestClass')]], $calls[0]);

        $service = $container->getDefinition('handler_test');
        $calls = $service->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals(['pushProcessor', [new TypedReference('test2', 'TestClass')]], $calls[0]);

        $service = $container->getDefinition('monolog.handler.priority_test');
        $calls = $service->getMethodCalls();
        $this->assertCount(5, $calls);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor-10', 'TestClass')]], $calls[0]);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor+10', 'TestClass')]], $calls[1]);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor+20', 'TestClass')]], $calls[2]);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor+20', 'TestClass')]], $calls[2]);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor+25+35', 'TestClass')]], $calls[3]);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor+35+25', 'TestClass')]], $calls[4]);

        $service = $container->getDefinition('monolog.handler.priority_test_2');
        $calls = $service->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor+35+25', 'TestClass')]], $calls[0]);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor+25+35', 'TestClass')]], $calls[1]);

        $service = $container->getDefinition('monolog.logger');
        $calls = $service->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals(['useMicrosecondTimestamps', ['%monolog.use_microseconds%']], $calls[0]);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor_all_channels+0', 'TestClass')]], $calls[1]);

        $service = $container->getDefinition('monolog.logger.test');
        $calls = $service->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor_test_channel-25', 'TestClass')]], $calls[0]);
        $this->assertEquals(['pushProcessor', [new TypedReference('processor_all_channels+0', 'TestClass')]], $calls[1]);
    }

    public function testFailureOnHandlerWithoutPushProcessor()
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('monolog.php');

        $service = new Definition(NullHandler::class);
        $service->addTag('monolog.processor', ['handler' => 'test3']);
        $container->setDefinition('monolog.handler.test3', $service);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->addCompilerPass(new AddProcessorsPass());

        if (Logger::API < 2) {
            $container->compile();
            $service = $container->getDefinition('monolog.handler.test3');
            $calls = $service->getMethodCalls();
            $this->assertCount(1, $calls);
        } else {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('The "test3" handler does not accept processors');
            $container->compile();
        }
    }

    protected function getContainer()
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('monolog.php');

        $container->setParameter('monolog.additional_channels', ['test']);
        $container->setParameter('monolog.handlers_to_channels', []);

        $definition = $container->getDefinition('monolog.logger_prototype');
        $container->setParameter('monolog.handler.console.class', ConsoleHandler::class);
        $container->setDefinition('monolog.handler.test', new Definition('%monolog.handler.console.class%', [100, false]));
        $container->setDefinition('handler_test', new Definition('%monolog.handler.console.class%', [100, false]));
        $container->setDefinition('monolog.handler.priority_test', new Definition('%monolog.handler.console.class%', [100, false]));
        $container->setDefinition('monolog.handler.priority_test_2', new Definition('%monolog.handler.console.class%', [100, false]));
        $container->setAlias('monolog.handler.test2', 'handler_test');
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.test')]);
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.test2')]);
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.priority_test')]);
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.priority_test_2')]);

        $service = new Definition('TestClass');
        $service->addTag('monolog.processor', ['handler' => 'test']);
        $container->setDefinition('test', $service);

        $service = new Definition('TestClass');
        $service->addTag('monolog.processor', ['handler' => 'test2']);
        $container->setDefinition('test2', $service);

        $service = new Definition('TestClass');
        $service->addTag('monolog.processor', ['handler' => 'priority_test', 'priority' => 10]);
        $container->setDefinition('processor+10', $service);

        $service = new Definition('TestClass');
        $service->addTag('monolog.processor', ['handler' => 'priority_test', 'priority' => -10]);
        $container->setDefinition('processor-10', $service);

        $service = new Definition('TestClass');
        $service->addTag('monolog.processor', ['handler' => 'priority_test', 'priority' => 20]);
        $container->setDefinition('processor+20', $service);

        $service = new Definition('TestClass');
        $service->addTag('monolog.processor', ['handler' => 'priority_test', 'priority' => 35]);
        $service->addTag('monolog.processor', ['handler' => 'priority_test_2', 'priority' => 25]);
        $container->setDefinition('processor+35+25', $service);

        $service = new Definition('TestClass');
        $service->addTag('monolog.processor', ['handler' => 'priority_test', 'priority' => 25]);
        $service->addTag('monolog.processor', ['handler' => 'priority_test_2', 'priority' => 35]);
        $container->setDefinition('processor+25+35', $service);

        $service = new Definition('TestClass');
        $service->addTag('monolog.processor', ['priority' => 0]);
        $container->setDefinition('processor_all_channels+0', $service);

        $service = new Definition('TestClass');
        $service->addTag('monolog.processor', ['channel' => 'test', 'priority' => -25]);
        $container->setDefinition('processor_test_channel-25', $service);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->addCompilerPass($channelPass = new LoggerChannelPass());
        $container->addCompilerPass(new AddProcessorsPass($channelPass));
        $container->compile();

        return $container;
    }
}
