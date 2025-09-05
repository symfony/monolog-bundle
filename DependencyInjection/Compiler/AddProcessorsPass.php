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

use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers processors in Monolog loggers or handlers.
 *
 * @author Christophe Coevoet <stof@notk.org>
 *
 * @internal since 3.9.0
 */
class AddProcessorsPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $channelPass;

    public function __construct(?LoggerChannelPass $channelPass = null)
    {
        $this->channelPass = $channelPass;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('monolog.logger')) {
            return;
        }

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
                $loggerId = 'app' === $tag['channel'] ? 'monolog.logger' : \sprintf('monolog.logger.%s', $tag['channel']);
                $definitions = [$container->getDefinition($loggerId)];
            } else {
                if ($this->channelPass) {
                    $definitions = [];
                    foreach ($this->channelPass->getChannels() as $channel) {
                        $loggerId = 'app' === $channel ? 'monolog.logger' : \sprintf('monolog.logger.%s', $channel);
                        $definitions[] = $container->getDefinition($loggerId);
                    }
                } else {
                    $definitions = [$container->getDefinition('monolog.logger_prototype')];
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
