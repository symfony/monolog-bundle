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
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class AddProcessorsPassTest extends TestCase
{
    public function testHandlerProcessors()
    {
        $container = $this->getContainer();

        $service = $container->getDefinition('monolog.handler.test');
        $calls = $service->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals(['pushProcessor', [new Reference('test')]], $calls[0]);

        $service = $container->getDefinition('handler_test');
        $calls = $service->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals(['pushProcessor', [new Reference('test2')]], $calls[0]);
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

    /**
     * @dataProvider provideEmptyTagsData
     */
    public function testEmptyTagsAreIgnoredWhenNonEmptyArePresent(
        array $tagAttributesList,
        array $expectedLoggerCalls,
        array $expectedMyChannelLoggerCalls
    ) {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('monolog.php');

        $container->setParameter('monolog.additional_channels', ['my_channel']);
        $container->setParameter('monolog.handlers_to_channels', []);

        $container->register('TestClass')->setTags(['monolog.processor' => $tagAttributesList]);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->addCompilerPass(new ResolveChildDefinitionsPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new LoggerChannelPass());
        $container->addCompilerPass(new AddProcessorsPass());
        $container->compile();

        $this->assertEquals($expectedLoggerCalls, $container->getDefinition('monolog.logger')->getMethodCalls());
        $this->assertEquals($expectedMyChannelLoggerCalls, $container->getDefinition('monolog.logger.my_channel')->getMethodCalls());
    }

    public static function provideEmptyTagsData(): iterable
    {
        yield 'with empty tag' => [
            [[]],
            [['pushProcessor', [new Reference('TestClass')]], ['useMicrosecondTimestamps', ['%monolog.use_microseconds%']]],
            [['pushProcessor', [new Reference('TestClass')]]],
        ];

        yield 'with app channel' => [
            [[], ['channel' => 'app']],
            [['useMicrosecondTimestamps', ['%monolog.use_microseconds%']], ['pushProcessor', [new Reference('TestClass')]]],
            [],
        ];

        yield 'with my_channel channel' => [
            [[], ['channel' => 'my_channel']],
            [['useMicrosecondTimestamps', ['%monolog.use_microseconds%']]],
            [['pushProcessor', [new Reference('TestClass')]]],
        ];

        yield 'with method and no channel' => [
            [[], ['method' => 'foo']],
            [['pushProcessor', [[new Reference('TestClass'), 'foo']]], ['useMicrosecondTimestamps', ['%monolog.use_microseconds%']]],
            [['pushProcessor', [[new Reference('TestClass'), 'foo']]]],
        ];
    }

    protected function getContainer()
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('monolog.php');

        $definition = $container->getDefinition('monolog.logger_prototype');
        $container->setParameter('monolog.handler.console.class', ConsoleHandler::class);
        $container->setDefinition('monolog.handler.test', new Definition('%monolog.handler.console.class%', [100, false]));
        $container->setDefinition('handler_test', new Definition('%monolog.handler.console.class%', [100, false]));
        $container->setAlias('monolog.handler.test2', 'handler_test');
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.test')]);
        $definition->addMethodCall('pushHandler', [new Reference('monolog.handler.test2')]);

        $service = new Definition('TestClass', ['false', new Reference('logger')]);
        $service->addTag('monolog.processor', ['handler' => 'test']);
        $container->setDefinition('test', $service);

        $service = new Definition('TestClass', ['false', new Reference('logger')]);
        $service->addTag('monolog.processor', ['handler' => 'test2']);
        $container->setDefinition('test2', $service);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->addCompilerPass(new AddProcessorsPass());
        $container->compile();

        return $container;
    }
}
