<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\AddSwiftMailerTransportPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\LoggerChannelPass;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\DebugHandlerPass;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\AddProcessorsPass;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\FixEmptyLoggerPass;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @finalsince 3.9.0
 */
class MonologBundle extends Bundle
{
    /**
     * @return void
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass($channelPass = new LoggerChannelPass());
        $container->addCompilerPass(new FixEmptyLoggerPass($channelPass));
        $container->addCompilerPass(new AddProcessorsPass());
        $container->addCompilerPass(new AddSwiftMailerTransportPass());
    }

    /**
     * @internal
     * @return void
     */
    public static function includeStacktraces(HandlerInterface $handler)
    {
        $formatter = $handler->getFormatter();
        if ($formatter instanceof LineFormatter || $formatter instanceof JsonFormatter) {
            $formatter->includeStacktraces();
        }
    }
}
