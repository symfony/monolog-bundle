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

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces the default logger by another one with its own channel for tagged services.
 *
 * @author Christophe Coevoet <stof@notk.org>
 *
 * @internal
 */
class LoggerChannelPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('monolog.logger')) {
            return;
        }

        /** @var list<string> $createdLoggers */
        $createdLoggers = ['app'];

        // create channels necessary for the handlers
        foreach ($container->findTaggedServiceIds('monolog.logger') as $id => $tags) {
            foreach ($tags as $tag) {
                if (empty($tag['channel']) || 'app' === $tag['channel']) {
                    continue;
                }

                $resolvedChannel = $container->getParameterBag()->resolveValue($tag['channel']);

                $definition = $container->getDefinition($id);
                $loggerId = \sprintf('monolog.logger.%s', $resolvedChannel);
                $this->createLogger($resolvedChannel, $loggerId, $container, $createdLoggers);

                foreach ($definition->getArguments() as $index => $argument) {
                    if ($argument instanceof Reference && 'logger' === (string) $argument) {
                        $definition->replaceArgument($index, $this->changeReference($argument, $loggerId));
                    }
                }

                $calls = $definition->getMethodCalls();
                foreach ($calls as $i => $call) {
                    foreach ($call[1] as $index => $argument) {
                        if ($argument instanceof Reference && 'logger' === (string) $argument) {
                            $calls[$i][1][$index] = $this->changeReference($argument, $loggerId);
                        }
                    }
                }
                $definition->setMethodCalls($calls);

                $binding = new BoundArgument(new Reference($loggerId));

                // Mark the binding as used already, to avoid reporting it as unused if the service does not use a
                // logger injected through the LoggerInterface alias.
                $values = $binding->getValues();
                $values[2] = true;
                $binding->setValues($values);

                $bindings = $definition->getBindings();
                $bindings[LoggerInterface::class] = $binding;
                $definition->setBindings($bindings);
            }
        }

        // create additional channels
        foreach ($container->getParameter('monolog.additional_channels') as $chan) {
            if ('app' === $chan) {
                continue;
            }
            $loggerId = \sprintf('monolog.logger.%s', $chan);
            $this->createLogger($chan, $loggerId, $container, $createdLoggers);
            $container->getDefinition($loggerId)->setPublic(true);
        }
        $container->getParameterBag()->remove('monolog.additional_channels');

        // wire handlers to channels
        $handlersToChannels = $container->getParameter('monolog.handlers_to_channels');
        foreach ($handlersToChannels as $handler => $channels) {
            foreach ($this->processChannels($channels, $createdLoggers) as $channel) {
                try {
                    $logger = $container->getDefinition('app' === $channel ? 'monolog.logger' : 'monolog.logger.'.$channel);
                } catch (InvalidArgumentException $e) {
                    throw new \InvalidArgumentException(\sprintf('Monolog configuration error: The logging channel "%s" assigned to the "%s" handler does not exist.', $channel, substr($handler, 16)), 0, $e);
                }
                $logger->addMethodCall('pushHandler', [new Reference($handler)]);
            }
        }
    }

    protected function processChannels(?array $configuration, array $createdLoggers): array
    {
        if (null === $configuration) {
            return $createdLoggers;
        }

        if ('inclusive' === $configuration['type']) {
            return $configuration['elements'] ?: $createdLoggers;
        }

        return array_diff($createdLoggers, $configuration['elements']);
    }

    /**
     * Create new logger from the monolog.logger_prototype.
     */
    protected function createLogger(string $channel, string $loggerId, ContainerBuilder $container, array &$createdLoggers): void
    {
        if (!\in_array($channel, $createdLoggers, true)) {
            $logger = new ChildDefinition('monolog.logger_prototype');
            $logger->replaceArgument(0, $channel);
            $logger->addTag('monolog.channel_logger');
            $container->setDefinition($loggerId, $logger);
            $createdLoggers[] = $channel;
        }

        $container->registerAliasForArgument($loggerId, LoggerInterface::class, $channel.'.logger');
    }

    /**
     * Creates a copy of a reference and alters the service ID.
     */
    private function changeReference(Reference $reference, string $serviceId): Reference
    {
        return new Reference($serviceId, $reference->getInvalidBehavior());
    }
}
