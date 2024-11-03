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
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\AddSwiftMailerTransportPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class AddSwiftMailerTransportPassTest extends TestCase
{
    private $compilerPass;

    private $container;

    private $definition;

    protected function setUp(): void
    {
        $this->compilerPass = new AddSwiftMailerTransportPass();
        $this->definition = new Definition(null, [new Reference('swiftmailer')]);
        $this->container = new ContainerBuilder();
        $this->container->setParameter('monolog.swift_mailer.handlers', ['foo']);
        $this->container->setDefinition('foo', $this->definition);
    }

    public function testWithRealTransport()
    {
        $this->container->register('swiftmailer.transport.real');

        $this->compilerPass->process($this->container);

        $this->assertEquals([['setTransport', [new Reference('swiftmailer.transport.real')]]], $this->definition->getMethodCalls());
    }

    public function testWithoutRealTransport()
    {
        $this->container->register('swiftmailer.transport');

        $this->compilerPass->process($this->container);

        $this->assertEquals([['setTransport', [new Reference('swiftmailer.transport')]]], $this->definition->getMethodCalls());
    }
}
