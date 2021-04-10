<?php

namespace Symfony\Bundle\MonologBundle\Tests\DependencyInjection\Compiler;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\ChannelsToMessengerPass;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\LoggerChannelPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class ChannelsToMessengerPassTest extends TestCase
{
    public function testProcess()
    {
        eval(<<<EOT
namespace Symfony\Bridge\Monolog\Messenger;

class ResetLoggersWorkerSubscriber
{
}
EOT
);
        $channelPass = $this->createMock(LoggerChannelPass::class);
        $channelPass
            ->method('getChannels')
            ->willReturn(['app', 'foo']);

        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('messenger.xml');

        $container
            ->getDefinition('monolog.messenger.event_subscriber.reset_loggers_worker')
            ->setPublic(true);

        $channelAppDef = new Definition(Logger::class);
        $channelAppDef->addArgument('app');
        $container->setDefinition('monolog.logger', $channelAppDef);

        $channelFooDef = new Definition(Logger::class);
        $channelFooDef->addArgument('foo');
        $container->setDefinition('monolog.logger.foo', $channelFooDef);

        $container->addCompilerPass(new ChannelsToMessengerPass($channelPass));

        $container->compile();

        $subscriberArguments = $container
            ->getDefinition('monolog.messenger.event_subscriber.reset_loggers_worker')
            ->getArgument(0);

        $this->assertEqualsCanonicalizing($subscriberArguments, [$channelAppDef, $channelFooDef]);
    }
}
