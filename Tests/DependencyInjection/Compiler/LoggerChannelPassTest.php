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

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\LoggerChannelPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class LoggerChannelPassTest extends TestCase
{
    public function testProcess()
    {
        $container = $this->getContainer();
        $this->assertTrue($container->hasDefinition('monolog.logger.test'), '->process adds a logger service for tagged service');

        $service = $container->getDefinition('test');
        $this->assertEquals('monolog.logger.test', (string) $service->getArgument(1), '->process replaces the logger by the new one');

        // pushHandlers for service "test"
        $expected = [
            'test' => ['monolog.handler.a', 'monolog.handler.b', 'monolog.handler.c'],
            'foo'  => ['monolog.handler.b'],
            'bar'  => ['monolog.handler.b', 'monolog.handler.c'],
        ];

        foreach ($expected as $serviceName => $handlers) {
            $service = $container->getDefinition($serviceName);
            $channel = $container->getDefinition((string) $service->getArgument(1));

            $calls = $channel->getMethodCalls();
            $this->assertCount(count($handlers), $calls);
            foreach ($handlers as $i => $handler) {
                list($methodName, $arguments) = $calls[$i];
                $this->assertEquals('pushHandler', $methodName);
                $this->assertCount(1, $arguments);
                $this->assertEquals($handler, (string) $arguments[0]);
            }
        }

        $this->assertNotNull($container->getDefinition('monolog.logger.additional'));
    }

    public function testConstructorInjectionWithMultipleTags()
    {
        $container = $this->getContainer();
        $this->assertTrue($container->hasDefinition('monolog.logger.foo'), '->process adds a foo logger service for tagged service');
        $this->assertTrue($container->hasDefinition('monolog.logger.bar'), '->process adds a bar logger service for tagged service');

        $service = $container->getDefinition('multiple');
        $this->assertEquals('monolog.logger.foo', (string) $service->getArgument(1), '->process replaces the logger by the new one');
        $this->assertEquals([
            'foo' => new Reference('monolog.logger.foo'),
            'bar' => new Reference('monolog.logger.bar'),
            'baz' => new Reference('logger'),
        ], $service->getArgument(2), '->process replaces loggers in the logger collection');
        $this->assertEquals([
            'foo' => new Reference('logger'),
            'bar' => new Reference('another.service'),
        ], $service->getArgument(3), '->process does not replace loggers in the logger collection');
    }

    public function testSetterInjectionWithMultipleTags()
    {
        $container = $this->getContainerWithSetter();
        $this->assertTrue($container->hasDefinition('monolog.logger.foo'), '->process adds a foo logger service for tagged service');
        $this->assertTrue($container->hasDefinition('monolog.logger.bar'), '->process adds a bar logger service for tagged service');

        $service = $container->getDefinition('multiple');
        $calls = $service->getMethodCalls();
        $this->assertEquals('monolog.logger.foo', (string) $calls[0][1][0], '->process replaces the logger by the new one in calls (first setter)');
        $this->assertEquals([
            'foo' => new Reference('monolog.logger.foo'),
            'bar' => new Reference('monolog.logger.bar'),
            'baz' => new Reference('logger'),
        ], $calls[0][1][1], '->process replaces loggers in the logger collection (calls, first setter)');
        $this->assertEquals('monolog.logger.foo', (string) $calls[1][1][0], '->process replaces the logger by the new one in calls (second setter)');
        $this->assertEquals([
            'foo' => new Reference('logger'),
            'bar' => new Reference('another.service'),
        ], $calls[1][1][1], '->process does not replace loggers in the logger collection (calls, second setter)');
    }

    public function testTypeHintedAliasesExistForEachChannel()
    {
        if (!\method_exists(ContainerBuilder::class, 'registerAliasForArgument')) {
            $this->markTestSkipped('Need DependencyInjection 4.2+ to register type-hinted aliases for channels.');
        }

        $container = $this->getContainer();
        $expectedChannels = ['test', 'foo', 'bar', 'additional'];

        foreach ($expectedChannels as $channelName) {
            $aliasName = LoggerInterface::class.' $' .$channelName.'Logger';
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
        if (!\method_exists('Symfony\Component\DependencyInjection\Definition', 'getBindings')) {
            $this->markTestSkipped('Need DependencyInjection 3.4+ to autowire channel logger.');
        }

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
        if (!\method_exists('Symfony\Component\DependencyInjection\Definition', 'getBindings')) {
            $this->markTestSkipped('Need DependencyInjection 3.4+ to autowire channel logger.');
        }

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
        if (!\method_exists('Symfony\Component\DependencyInjection\Definition', 'getBindings')) {
            $this->markTestSkipped('Need DependencyInjection 3.4+ to autowire channel logger.');
        }

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

    private function getContainer()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('monolog.xml');
        $definition = $container->getDefinition('monolog.logger_prototype');
        $container->set('monolog.handler.test', new Definition('%monolog.handler.null.class%', [100, false]));
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.test')]);

        // Handlers
        $container->set('monolog.handler.a', new Definition('%monolog.handler.null.class%', [100, false]));
        $container->set('monolog.handler.b', new Definition('%monolog.handler.null.class%', [100, false]));
        $container->set('monolog.handler.c', new Definition('%monolog.handler.null.class%', [100, false]));

        $container->set('another.service', new Definition(__CLASS__));

        // Channels
        $channelsByDef = [
            'test' => ['test'],
            'foo' => ['foo'],
            'bar' => ['bar'],
            'multiple' => ['foo', 'bar']
        ];

        foreach ($channelsByDef as $name => $channels) {
            $service = new Definition('TestClass', [
                'false',
                new Reference('logger'),
                ['foo' => new Reference('logger'), 'bar' => new Reference('logger'), 'baz' => new Reference('logger')],
                ['foo' => new Reference('logger'), 'bar' => new Reference('another.service')]
            ]);
            foreach ($channels as $channelName) {
                $service->addTag('monolog.logger', ['channel' => $channelName]);
            }
            $container->setDefinition($name, $service);
        }

        $container->setParameter('monolog.additional_channels', ['additional']);
        $container->setParameter('monolog.handlers_to_channels', [
            'monolog.handler.a' => [
                'type' => 'inclusive',
                'elements' => ['test']
            ],
            'monolog.handler.b' => null,
            'monolog.handler.c' => [
                'type' => 'exclusive',
                'elements' => ['foo']
            ]
        ]);

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
        $container->set('monolog.handler.test', new Definition('%monolog.handler.null.class%', [100, false]));
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.test')]);

        $container->set('another.service', new Definition(__CLASS__));

        // Channels
        $service = new Definition('TestClass');
        $service->addTag('monolog.logger', ['channel' => 'test']);
        $service->addMethodCall('setLogger', [new Reference('logger')]);
        $container->setDefinition('foo', $service);

        $service2 = new Definition('TestClass');
        $service2->addTag('monolog.logger', ['channel' => 'foo']);
        $service2->addTag('monolog.logger', ['channel' => 'bar']);
        $service2->addMethodCall('setLoggerOk', [
            new Reference('logger'),
            ['foo' => new Reference('logger'), 'bar' => new Reference('logger'), 'baz' => new Reference('logger')]
        ]);
        $service2->addMethodCall('setLoggerNok', [
            new Reference('logger'),
            ['foo' => new Reference('logger'), 'bar' => new Reference('another.service')]
        ]);
        $container->setDefinition('multiple', $service2);

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
