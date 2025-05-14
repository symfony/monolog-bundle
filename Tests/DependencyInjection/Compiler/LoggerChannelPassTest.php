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
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\LoggerChannelPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

class LoggerChannelPassTest extends TestCase
{
    public function testProcess()
    {
        $container = $this->getContainer();
        $this->assertTrue($container->hasDefinition('monolog.logger.test'), '->process adds a logger service for tagged service');

        $service = $container->getDefinition('test');
        $this->assertEquals('monolog.logger.test', (string) $service->getArgument(1), '->process replaces the logger by the new one');

        $service = $container->getDefinition('test');
        $logger = $container->getDefinition((string) $service->getArgument(1));
        $calls = $logger->getMethodCalls();
        $this->assertCount(4, $calls);
        $this->assertEquals(['pushHandler', [new Reference('monolog.handler.a')]], $calls[0]);
        $this->assertEquals(['pushHandler', [new Reference('monolog.handler.b')]], $calls[1]);
        $this->assertEquals(['pushHandler', [new Reference('monolog.handler.c')]], $calls[2]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.-10', 'SomeProcessor')]], $calls[3]);

        $service = $container->getDefinition('foo');
        $logger = $container->getDefinition((string) $service->getArgument(1));
        $calls = $logger->getMethodCalls();
        $this->assertCount(3, $calls);
        $this->assertEquals(['pushHandler', [new Reference('monolog.handler.b')]], $calls[0]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.-10', 'SomeProcessor')]], $calls[1]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.foo+10', 'SomeProcessor')]], $calls[2]);

        $service = $container->getDefinition('bar');
        $logger = $container->getDefinition((string) $service->getArgument(1));
        $calls = $logger->getMethodCalls();
        $this->assertCount(3, $calls);
        $this->assertEquals(['pushHandler', [new Reference('monolog.handler.b')]], $calls[0]);
        $this->assertEquals(['pushHandler', [new Reference('monolog.handler.c')]], $calls[1]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.-10', 'SomeProcessor')]], $calls[2]);

        $logger = $container->getDefinition('monolog.logger');
        $calls = $logger->getMethodCalls();
        $this->assertCount(5, $calls);
        $this->assertEquals(['useMicrosecondTimestamps', ['%monolog.use_microseconds%']], $calls[0]);
        $this->assertEquals(['pushHandler', [new Reference('monolog.handler.b')]], $calls[1]);
        $this->assertEquals(['pushHandler', [new Reference('monolog.handler.c')]], $calls[2]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.-10', 'SomeProcessor')]], $calls[3]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.app', 'SomeProcessor')]], $calls[4]);

        $this->assertNotNull($container->getDefinition('monolog.logger.additional'));

        $handler = $container->getDefinition('monolog.handler.test');
        $calls = $handler->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.a', 'SomeProcessor')]], $calls[0]);

        $handler = $container->getDefinition('handler_test');
        $calls = $handler->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.b', 'SomeProcessor')]], $calls[0]);

        $handler = $container->getDefinition('monolog.handler.a');
        $calls = $handler->getMethodCalls();
        $this->assertCount(5, $calls);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.-10', 'SomeProcessor')]], $calls[0]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.+10', 'SomeProcessor')]], $calls[1]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.+20', 'SomeProcessor')]], $calls[2]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.+20', 'SomeProcessor')]], $calls[2]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.+25+35', 'SomeProcessor')]], $calls[3]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.+35+25', 'SomeProcessor')]], $calls[4]);

        $handler = $container->getDefinition('monolog.handler.b');
        $calls = $handler->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.+35+25', 'SomeProcessor')]], $calls[0]);
        $this->assertEquals(['pushProcessor', [new TypedReference('monolog.processor.handler.+25+35', 'SomeProcessor')]], $calls[1]);
    }

    public function testTypeHintedAliasesExistForEachChannel()
    {
        $container = $this->getContainer();
        $expectedChannels = ['test', 'foo', 'bar', 'additional'];

        foreach ($expectedChannels as $channelName) {
            $aliasName = LoggerInterface::class.' $'.$channelName.'Logger';
            $this->assertTrue($container->hasAlias($aliasName), 'type-hinted alias should be exists for each logger channel');
        }
    }

    public function testProcessSetters()
    {
        $container = $this->getContainerWithSetter();
        $this->assertTrue($container->hasDefinition('monolog.logger.test'), '->process adds a logger service for tagged service');

        $service = $container->getDefinition('foo');
        $calls = $service->getMethodCalls();
        $this->assertEquals('monolog.logger.test', (string) $calls[0][1][0], '->process replaces the logger by the new one in setters');
    }

    public function testAutowiredLoggerArgumentsAreReplacedWithChannelLogger()
    {
        $container = $this->getFunctionalContainer();

        $dummyService = $container->register('dummy_service', 'Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Compiler\DummyService')
            ->setAutowired(true)
            ->setPublic(true)
            ->addTag('monolog.logger', ['channel' => 'test']);

        $container->compile();

        $this->assertEquals('monolog.logger.test', (string) $dummyService->getArgument(0));
    }

    public function testAutowiredLoggerArgumentsAreReplacedWithChannelLoggerWhenAutoconfigured()
    {
        $container = $this->getFunctionalContainer();

        $container->registerForAutoconfiguration('Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Compiler\DummyService')
            ->setProperty('fake', 'dummy');

        $container->register('dummy_service', 'Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Compiler\DummyService')
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true)
            ->addTag('monolog.logger', ['channel' => 'test']);

        $container->compile();

        $this->assertEquals('monolog.logger.test', (string) $container->getDefinition('dummy_service')->getArgument(0));
    }

    public function testAutowiredLoggerArgumentsAreNotReplacedWithChannelLoggerIfLoggerArgumentIsConfiguredExplicitly()
    {
        $container = $this->getFunctionalContainer();

        $dummyService = $container->register('dummy_service', 'Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Compiler\DummyService')
            ->setAutowired(true)
            ->addArgument(new Reference('monolog.logger'))
            ->addTag('monolog.logger', ['channel' => 'test']);

        $container->compile();

        $this->assertEquals('monolog.logger', (string) $dummyService->getArgument(0));
    }

    public function testTagNotBreakingIfNoLogger()
    {
        $container = $this->getFunctionalContainer();

        $dummyService = $container->register('dummy_service', 'stdClass')
            ->addTag('monolog.logger', ['channel' => 'test']);

        $container->compile();

        $this->assertEquals([], $dummyService->getArguments());
    }

    public function testChannelsConfigurationOptionSupportsAppChannel()
    {
        $container = $this->getFunctionalContainer();

        $container->setParameter('monolog.additional_channels', ['app']);
        $container->compile();

        // the test ensures that the validation does not fail (i.e. it does not throw any exceptions)
        $this->addToAssertionCount(1);
    }

    public function testFailureOnHandlerWithoutPushProcessor()
    {
        $container = $this->getFunctionalContainer();

        $service = new Definition(NullHandler::class);
        $service->addTag('monolog.processor', ['handler' => 'test3']);
        $container->setDefinition('monolog.handler.test3', $service);

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

    private function getContainer()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('monolog.xml');
        $definition = $container->getDefinition('monolog.logger_prototype');
        $container->setDefinition('monolog.handler.test', new Definition(ConsoleHandler::class));
        $container->setDefinition('handler_test', new Definition(ConsoleHandler::class));
        $container->setAlias('monolog.handler.test2', 'handler_test');
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.test')]);

        // Handlers
        $container->setDefinition('monolog.handler.a', new Definition(ConsoleHandler::class));
        $container->setDefinition('monolog.handler.b', new Definition(ConsoleHandler::class));
        $container->setDefinition('monolog.handler.c', new Definition(ConsoleHandler::class));

        // Channels
        foreach (['test', 'foo', 'bar'] as $name) {
            $service = new Definition('SomeClass', ['false', new Reference('logger')]);
            $service->addTag('monolog.logger', ['channel' => $name]);
            $container->setDefinition($name, $service);
        }

        $container->setParameter('monolog.additional_channels', ['additional']);
        $container->setParameter('monolog.handlers_to_channels', [
            'monolog.handler.a' => [
                'type' => 'inclusive',
                'elements' => ['test'],
            ],
            'monolog.handler.b' => null,
            'monolog.handler.c' => [
                'type' => 'exclusive',
                'elements' => ['foo'],
            ],
        ]);

        // Processors
        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['handler' => 'test']);
        $container->setDefinition('monolog.processor.handler.a', $service);

        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['handler' => 'test2']);
        $container->setDefinition('monolog.processor.handler.b', $service);

        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['handler' => 'a', 'priority' => 10]);
        $container->setDefinition('monolog.processor.handler.+10', $service);

        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['handler' => 'a', 'priority' => -10]);
        $container->setDefinition('monolog.processor.handler.-10', $service);

        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['handler' => 'a', 'priority' => 20]);
        $container->setDefinition('monolog.processor.handler.+20', $service);

        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['handler' => 'a', 'priority' => 35]);
        $service->addTag('monolog.processor', ['handler' => 'b', 'priority' => 25]);
        $container->setDefinition('monolog.processor.handler.+35+25', $service);

        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['handler' => 'a', 'priority' => 25]);
        $service->addTag('monolog.processor', ['handler' => 'b', 'priority' => 35]);
        $container->setDefinition('monolog.processor.handler.+25+35', $service);

        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['priority' => -10]);
        $container->setDefinition('monolog.processor.-10', $service);

        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['channel' => 'app']);
        $container->setDefinition('monolog.processor.app', $service);

        $service = new Definition('SomeProcessor');
        $service->addTag('monolog.processor', ['channel' => 'foo', 'priority' => 10]);
        $container->setDefinition('monolog.processor.foo+10', $service);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->addCompilerPass(new LoggerChannelPass());
        $container->compile();

        return $container;
    }

    private function getContainerWithSetter()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('monolog.xml');
        $definition = $container->getDefinition('monolog.logger_prototype');
        $container->setDefinition('monolog.handler.test', new Definition(ConsoleHandler::class));
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.test')]);

        // Channels
        $service = new Definition('SomeClass');
        $service->addTag('monolog.logger', ['channel' => 'test']);
        $service->addMethodCall('setLogger', [new Reference('logger')]);
        $container->setDefinition('foo', $service);

        $container->setParameter('monolog.additional_channels', ['additional']);
        $container->setParameter('monolog.handlers_to_channels', []);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->addCompilerPass(new LoggerChannelPass());
        $container->compile();

        return $container;
    }

    /**
     * @return ContainerBuilder
     */
    private function getFunctionalContainer()
    {
        $container = new ContainerBuilder();
        $container->setParameter('monolog.additional_channels', []);
        $container->setParameter('monolog.handlers_to_channels', []);
        $container->setParameter('monolog.use_microseconds', true);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('monolog.xml');

        $container->addCompilerPass(new LoggerChannelPass());

        // disable removing passes to be able to inspect the container before all the inlining optimizations
        $container->getCompilerPassConfig()->setRemovingPasses([]);

        return $container;
    }
}

class DummyService
{
    public function __construct(LoggerInterface $logger)
    {
    }
}
