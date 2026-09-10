<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection\Handler;

use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossedHandler;
use Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class FingersCrossedHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'fingers_crossed';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->scalarNode('handler')->end()
                ->scalarNode('action_level')->defaultValue('WARNING')->end()
                ->scalarNode('activation_strategy')->defaultNull()->end()
                ->scalarNode('buffer_size')->defaultValue(0)->end()
                ->booleanNode('stop_buffering')->defaultTrue()->end()
                ->scalarNode('passthru_level')->defaultNull()->end()
                ->arrayNode('excluded_http_codes')
                    ->info('Only for "fingers_crossed" handler type')
                    ->example([403, 404, [400 => ['^/foo', '^/bar']]])
                    ->canBeUnset()
                    ->beforeNormalization()
                        ->always(static function ($values) {
                            if (false === $values) {
                                return false;
                            }

                            return array_map(static function ($value) {
                                if (\is_array($value)) {
                                    return isset($value['code']) ? $value : ['code' => key($value), 'urls' => current($value)];
                                }

                                return ['code' => $value, 'urls' => []];
                            }, $values);
                        })
                    ->end()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('code')->end()
                            ->arrayNode('urls')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->example([
                'handler' => 'nested',
                'action_level' => 'WARNING',
                'buffer_size' => 0,
                'stop_buffering' => true,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->validate()
                ->ifTrue(static function ($v) { return 'fingers_crossed' === $v['type'] && empty($v['fingers_crossed']['handler'] ?? null); })
                ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler, BufferHandler, FilterHandler, DeduplicationHandler or SamplingHandler')
            ->end()
            ->validate()
                ->ifTrue(static function ($v) { return 'fingers_crossed' === $v['type'] && !empty($v['fingers_crossed']['excluded_http_codes'] ?? []) && !empty($v['fingers_crossed']['activation_strategy'] ?? null); })
                ->thenInvalid('You can not use excluded_http_codes together with a custom activation_strategy in a FingersCrossedHandler')
            ->end()
        ;
    }

    /**
     * @param array{
     *     handler: string|null,
     *     action_level: string|int,
     *     activation_strategy: string|null,
     *     buffer_size: int,
     *     stop_buffering: bool,
     *     passthru_level: string|int|null,
     *     excluded_http_codes?: list<array{code: int, urls: list<string>}>,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $nestedHandlerId = $context->getHandlerId($handler['handler']);
        $context->markNestedHandler($nestedHandlerId);

        $activation = new Definition(ErrorLevelActivationStrategy::class, [$handler['action_level']]);

        if (isset($handler['activation_strategy'])) {
            $activation = new Reference($handler['activation_strategy']);
        } elseif (!empty($handler['excluded_http_codes'])) {
            $activationDef = new Definition(HttpCodeActivationStrategy::class, [
                new Reference('request_stack'),
                $handler['excluded_http_codes'],
                $activation,
            ]);
            $context->container->setDefinition($context->handlerId.'.http_code_strategy', $activationDef);
            $activation = new Reference($context->handlerId.'.http_code_strategy');
        }

        $definition = new Definition(FingersCrossedHandler::class);
        $definition->setArguments([
            new Reference($nestedHandlerId),
            $activation,
            $handler['buffer_size'],
            $config['bubble'],
            $handler['stop_buffering'],
            $handler['passthru_level'],
        ]);

        return $definition;
    }
}
