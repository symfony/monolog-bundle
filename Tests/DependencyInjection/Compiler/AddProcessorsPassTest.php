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
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\AddProcessorsPass;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

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

    protected function getContainer()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('monolog.xml');

        $definition = $container->getDefinition('monolog.logger_prototype');
        $container->setDefinition('monolog.handler.test', new Definition('%monolog.handler.null.class%', [100, false]));
        $container->setDefinition('handler_test', new Definition('%monolog.handler.null.class%', [100, false]));
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
