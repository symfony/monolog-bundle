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
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces the default logger by another one with its own channel for tagged services.
 *
 * @author Christophe Coevoet <stof@notk.org>
 *
 * @internal since 3.9.0
 */
class LoggerChannelPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    protected $channels = ['app'];

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('monolog.logger')) {
            return;
        }

        // create channels necessary for the handlers
        foreach ($container->findTaggedServiceIds('monolog.logger') as $id => $tags) {
            foreach ($tags as $tag) {
                if (empty($tag['channel']) || 'app' === $tag['channel']) {
                    continue;
                }

                $resolvedChannel = $container->getParameterBag()->resolveValue($tag['channel']);

                $definition = $container->getDefinition($id);
                $loggerId = \sprintf('monolog.logger.%s', $resolvedChannel);
                $this->createLogger($resolvedChannel, $loggerId, $container);

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
                $bindings['Psr\Log\LoggerInterface'] = $binding;
                $definition->setBindings($bindings);
            }
        }

        // create additional channels
        foreach ($container->getParameter('monolog.additional_channels') as $chan) {
            if ('app' === $chan) {
                continue;
            }
            $loggerId = \sprintf('monolog.logger.%s', $chan);
            $this->createLogger($chan, $loggerId, $container);
            $container->getDefinition($loggerId)->setPublic(true);
        }
        $container->getParameterBag()->remove('monolog.additional_channels');

        // wire handlers to channels
        $handlersToChannels = $container->getParameter('monolog.handlers_to_channels');
        foreach ($handlersToChannels as $handler => $channels) {
            foreach ($this->processChannels($channels) as $channel) {
                try {
                    $logger = $container->getDefinition('app' === $channel ? 'monolog.logger' : 'monolog.logger.'.$channel);
                } catch (InvalidArgumentException $e) {
                    $msg = 'Monolog configuration error: The logging channel "'.$channel.'" assigned to the "'.substr($handler, 16).'" handler does not exist.';
                    throw new \InvalidArgumentException($msg, 0, $e);
                }
                $logger->addMethodCall('pushHandler', [new Reference($handler)]);
            }
        }

        $this->addProcessors($container);
    }

    /**
     * @return array
     */
    public function getChannels()
    {
        return $this->channels;
    }

    /**
     * @return array
     */
    protected function processChannels(?array $configuration)
    {
        if (null === $configuration) {
            return $this->channels;
        }

        if ('inclusive' === $configuration['type']) {
            return $configuration['elements'] ?: $this->channels;
        }

        return array_diff($this->channels, $configuration['elements']);
    }

    /**
     * Create new logger from the monolog.logger_prototype.
     *
     * @return void
     */
    protected function createLogger(string $channel, string $loggerId, ContainerBuilder $container)
    {
        if (!\in_array($channel, $this->channels)) {
            $logger = new ChildDefinition('monolog.logger_prototype');
            $logger->replaceArgument(0, $channel);
            $container->setDefinition($loggerId, $logger);
            $this->channels[] = $channel;
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

    private function addProcessors(ContainerBuilder $container)
    {
        $indexedTags = [];
        $i = 1;

        foreach ($container->findTaggedServiceIds('monolog.processor') as $id => $tags) {
            foreach ($tags as &$tag) {
                $indexedTags[$tag['index'] = $i++] = $tag;
            }
            unset($tag);
            $definition = $container->getDefinition($id);
            $definition->setTags(array_merge($definition->getTags(), ['monolog.processor' => $tags]));
        }

        $taggedIteratorArgument = new TaggedIteratorArgument('monolog.processor', 'index', null, true);
        // array_reverse is used because ProcessableHandlerTrait::pushProcessor prepends processors to the beginning of the stack
        foreach (array_reverse($this->findAndSortTaggedServices($taggedIteratorArgument, $container), true) as $index => $reference) {
            $tag = $indexedTags[$index];

            if (!empty($tag['channel']) && !empty($tag['handler'])) {
                throw new \InvalidArgumentException(\sprintf('you cannot specify both the "handler" and "channel" attributes for the "monolog.processor" tag on service "%s"', $reference));
            }

            if (!empty($tag['handler'])) {
                $parentDef = $container->findDefinition(\sprintf('monolog.handler.%s', $tag['handler']));
                $definitions = [$parentDef];
                while (!$parentDef->getClass() && $parentDef instanceof ChildDefinition) {
                    $parentDef = $container->findDefinition($parentDef->getParent());
                }
                $class = $container->getParameterBag()->resolveValue($parentDef->getClass());
                if (!method_exists($class, 'pushProcessor')) {
                    throw new \InvalidArgumentException(\sprintf('The "%s" handler does not accept processors', $tag['handler']));
                }
            } elseif (!empty($tag['channel'])) {
                if ('app' === $tag['channel']) {
                    $definitions = [$container->getDefinition('monolog.logger')];
                } else {
                    $definitions = [$container->getDefinition(\sprintf('monolog.logger.%s', $tag['channel']))];
                }
            } else {
                $definitions = [$container->getDefinition('monolog.logger')];
                foreach ($this->channels as $channel) {
                    if ('app' === $channel) {
                        continue;
                    }

                    $definitions[] = $container->getDefinition(\sprintf('monolog.logger.%s', $channel));
                }
            }

            if (!empty($tag['method'])) {
                $processor = [$reference, $tag['method']];
            } else {
                // If no method is defined, fallback to use __invoke
                $processor = $reference;
            }

            foreach ($definitions as $definition) {
                $definition->addMethodCall('pushProcessor', [$processor]);
            }
        }
    }
}
