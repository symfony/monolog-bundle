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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Register channels to messenger subscriber.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
class ChannelsToMessengerPass implements CompilerPassInterface
{
    private $channelPass;

    public function __construct(LoggerChannelPass $channelPass)
    {
        $this->channelPass = $channelPass;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('monolog.messenger.event_subscriber.reset_loggers_worker')) {
            return;
        }

        $channelRefs = [];
        foreach ($this->channelPass->getChannels() as $channel) {
            $channelRefs[] = new Reference('app' === $channel ? 'monolog.logger' : 'monolog.logger.'.$channel);
        }

        $container
            ->getDefinition('monolog.messenger.event_subscriber.reset_loggers_worker')
            ->replaceArgument(0, $channelRefs)
        ;
    }
}
