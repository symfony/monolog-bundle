<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Monolog\Logger;

/**
 * Adds the DebugHandler when the profiler is enabled.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DebugHandlerPass implements CompilerPassInterface
{
    private $channelPass;

    public function __construct(LoggerChannelPass $channelPass)
    {
        $this->channelPass = $channelPass;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('profiler')) {
            return;
        }

        // detect if the profiler is present but will be disabled
        $enabled = true;
        foreach ($container->getDefinition('profiler')->getMethodCalls() as $call) {
            if ($call[0] === 'disable') {
                $enabled = false;
            } elseif ($call[0] === 'enable') {
                $enabled = true;
            }
        }
        if (!$enabled) {
            return;
        }

        $debugHandler = new Definition('%monolog.handler.debug.class%', array(Logger::DEBUG, true));
        $container->setDefinition('monolog.handler.debug', $debugHandler);

        foreach ($this->channelPass->getChannels() as $channel) {
            $container
                ->getDefinition($channel === 'app' ? 'monolog.logger' : 'monolog.logger.'.$channel)
                ->addMethodCall('pushHandler', array(new Reference('monolog.handler.debug')));
        }
    }
}
