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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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
        $this->assertDICConstructorArguments($handler, array('%kernel.logs_dir%/%kernel.environment%.log', \Monolog\Logger::DEBUG, true, null));
    }

    public function testLoadWithCustomValues()
    {
        $container = $this->getContainer(array(array('handlers' => array(
            'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR', 'file_permission' => '0666')
        ))));
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::ERROR, false, 0666));
    }

    public function testLoadWithNestedHandler()
    {
        $container = $this->getContainer(array(array('handlers' => array(
            'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR', 'file_permission' => '0666'),
            'nested' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR', 'file_permission' => '0666', 'nested' => true)
        ))));
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));

        $logger = $container->getDefinition('monolog.logger');
        // Nested handler must not be pushed to logger
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::ERROR, false, 0666));
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
    public function testExceptionWhenUsingFilterWithoutHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'filter')))), $container);
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

    public function testSyslogHandlerWithLogopts()
    {
        $container = $this->getContainer(array(array('handlers' => array('main' => array('type' => 'syslog', 'logopts' => LOG_CONS)))));

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.syslog.class%');
        $this->assertDICConstructorArguments($handler, array(false, 'user', \Monolog\Logger::DEBUG, true, LOG_CONS));
    }

    public function testRollbarHandlerCreatesNotifier()
    {
        $container = $this->getContainer(array(array('handlers' => array('main' => array('type' => 'rollbar', 'token' => 'MY_TOKEN')))));

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.rollbar.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('monolog.rollbar.notifier.1c8e6a67728dff6a209f828427128dd8b3c2b746'), \Monolog\Logger::DEBUG, true));
    }

    public function testRollbarHandlerReusesNotifier()
    {
        $container = $this->getContainer(array(array('handlers' => array('main' => array('type' => 'rollbar', 'id' => 'my_rollbar_id')))));

        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.rollbar.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('my_rollbar_id'), \Monolog\Logger::DEBUG, true));
    }

    public function testSocketHandler()
    {
        try {
            $this->getContainer(array(array('handlers' => array('socket' => array('type' => 'socket')))));
            $this->fail();
        } catch (InvalidConfigurationException $e) {
            $this->assertContains('connection_string', $e->getMessage());
        }

        $container = $this->getContainer(array(array('handlers' => array('socket' => array(
            'type' => 'socket', 'timeout' => 1, 'persistent' => true,
            'connection_string' => 'localhost:50505', 'connection_timeout' => '0.6')
        ))));
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.socket'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.socket')));

        $handler = $container->getDefinition('monolog.handler.socket');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.socket.class%');
        $this->assertDICConstructorArguments($handler, array('localhost:50505', \Monolog\Logger::DEBUG, true));
        $this->assertDICDefinitionMethodCallAt(0, $handler, 'setTimeout', array('1'));
        $this->assertDICDefinitionMethodCallAt(1, $handler, 'setConnectionTimeout', array('0.6'));
        $this->assertDICDefinitionMethodCallAt(2, $handler, 'setPersistent', array(true));

    }

    public function testRavenHandlerWhenConfigurationIsWrong()
    {
        try {
            $this->getContainer(array(array('handlers' => array('raven' => array('type' => 'raven')))));
            $this->fail();
        } catch (InvalidConfigurationException $e) {
            $this->assertContains('DSN', $e->getMessage());
        }
    }

    public function testRavenHandlerWhenADSNIsSpecified()
    {
        $dsn = 'http://43f6017361224d098402974103bfc53d:a6a0538fc2934ba2bed32e08741b2cd3@marca.python.live.cheggnet.com:9000/1';

        $container = $this->getContainer(array(array('handlers' => array('raven' => array(
            'type' => 'raven', 'dsn' => $dsn)
        ))));
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.raven'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.raven')));

        $this->assertTrue($container->hasDefinition('monolog.raven.client.'.sha1($dsn)));

        $handler = $container->getDefinition('monolog.handler.raven');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.raven.class%');
    }

    public function testRavenHandlerWhenADSNAndAClientAreSpecified()
    {
        $container = $this->getContainer(array(array('handlers' => array('raven' => array(
            'type' => 'raven', 'dsn' => 'foobar', 'client_id' => 'raven.client')
        ))));

        $this->assertFalse($container->hasDefinition('raven.client'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.raven')));

        $handler = $container->getDefinition('monolog.handler.raven');
        $this->assertDICConstructorArguments($handler, array(new Reference('raven.client'), 100, true));
    }

    public function testRavenHandlerWhenAClientIsSpecified()
    {
        $container = $this->getContainer(array(array('handlers' => array('raven' => array(
            'type' => 'raven', 'client_id' => 'raven.client')
        ))));

        $this->assertFalse($container->hasDefinition('raven.client'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.raven')));

        $handler = $container->getDefinition('monolog.handler.raven');
        $this->assertDICConstructorArguments($handler, array(new Reference('raven.client'), 100, true));
    }

    public function testLogglyHandler()
    {
        $token = '026308d8-2b63-4225-8fe9-e01294b6e472';
        try {
            $this->getContainer(array(array('handlers' => array('loggly' => array('type' => 'loggly')))));
            $this->fail();
        } catch (InvalidConfigurationException $e) {
            $this->assertContains('token', $e->getMessage());
        }

        try {
            $this->getContainer(array(array('handlers' => array('loggly' => array(
                'type' => 'loggly', 'token' => $token, 'tags' => 'x, 1zone ,www.loggly.com,-us,apache$')
            ))));
            $this->fail();
        } catch (InvalidConfigurationException $e) {
            $this->assertContains('-us, apache$', $e->getMessage());
        }

        $container = $this->getContainer(array(array('handlers' => array('loggly' => array(
            'type' => 'loggly', 'token' => $token)
        ))));
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.loggly'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.loggly')));
        $handler = $container->getDefinition('monolog.handler.loggly');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.loggly.class%');
        $this->assertDICConstructorArguments($handler, array($token, \Monolog\Logger::DEBUG, true));
        $this->assertEmpty($handler->getMethodCalls());

        $container = $this->getContainer(array(array('handlers' => array('loggly' => array(
            'type' => 'loggly', 'token' => $token, 'tags' => array(' ', 'foo', '', 'bar'))
        ))));
        $handler = $container->getDefinition('monolog.handler.loggly');
        $this->assertDICDefinitionMethodCallAt(0, $handler, 'setTag', array('foo,bar'));

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
