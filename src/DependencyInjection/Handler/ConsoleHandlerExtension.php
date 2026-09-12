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

use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Definition;

class ConsoleHandlerExtension implements HandlerExtensionInterface
{
    public function getName(): string
    {
        return 'console';
    }

    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void
    {
        $handlerNode
            ->canBeUnset()
            ->children()
                ->arrayNode('verbosity_levels')
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($v) {
                            $map = [];
                            $verbosities = [
                                // allow numeric indexed array with ascending verbosity
                                0 => 'VERBOSITY_QUIET',
                                1 => 'VERBOSITY_NORMAL',
                                2 => 'VERBOSITY_VERBOSE',
                                3 => 'VERBOSITY_VERY_VERBOSE',
                                4 => 'VERBOSITY_DEBUG',
                                // or array indexed by verbosity constants
                                OutputInterface::VERBOSITY_QUIET => 'VERBOSITY_QUIET',
                                OutputInterface::VERBOSITY_NORMAL => 'VERBOSITY_NORMAL',
                                OutputInterface::VERBOSITY_VERBOSE => 'VERBOSITY_VERBOSE',
                                OutputInterface::VERBOSITY_VERY_VERBOSE => 'VERBOSITY_VERY_VERBOSE',
                                OutputInterface::VERBOSITY_DEBUG => 'VERBOSITY_DEBUG',
                            ];
                            // allow numeric indexed array with ascending verbosity, verbosity constants
                            // or lowercase names of the constants as keys
                            foreach ($v as $verbosity => $level) {
                                if (\is_int($verbosity) && isset($verbosities[$verbosity])) {
                                    $map[$verbosities[$verbosity]] = $level;
                                } else {
                                    $map[strtoupper($verbosity)] = $level;
                                }
                            }

                            return $map;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('VERBOSITY_QUIET')->defaultValue('ERROR')->end()
                        ->scalarNode('VERBOSITY_NORMAL')->defaultValue('WARNING')->end()
                        ->scalarNode('VERBOSITY_VERBOSE')->defaultValue('NOTICE')->end()
                        ->scalarNode('VERBOSITY_VERY_VERBOSE')->defaultValue('INFO')->end()
                        ->scalarNode('VERBOSITY_DEBUG')->defaultValue('DEBUG')->end()
                    ->end()
                    ->validate()
                        ->always(static function ($v) {
                            $map = [];
                            foreach ($v as $verbosity => $level) {
                                $verbosityConstant = OutputInterface::class.'::'.$verbosity;

                                if (!\defined($verbosityConstant)) {
                                    throw new InvalidConfigurationException(\sprintf('The configured verbosity "%s" is invalid as it is not defined in Symfony\Component\Console\Output\OutputInterface.', $verbosity));
                                }

                                try {
                                    $level = Logger::toMonologLevel($level)->value;
                                } catch (\Psr\Log\InvalidArgumentException $e) {
                                    throw new InvalidConfigurationException(\sprintf('The configured minimum log level "%s" for verbosity "%s" is invalid as it is not defined in Monolog\Logger.', $level, $verbosity));
                                }

                                $map[\constant($verbosityConstant)] = $level;
                            }

                            return $map;
                        })
                    ->end()
                ->end()
                ->variableNode('console_formatter_options')
                    ->defaultValue([])
                    ->validate()
                        ->ifTrue(static function ($v) { return !\is_array($v); })
                        ->thenInvalid('The console_formatter_options must be an array.')
                    ->end()
                ->end()
                ->booleanNode('interactive_only')->defaultFalse()->end()
            ->end()
            ->example([
                'verbosity_levels' => [
                    'VERBOSITY_NORMAL' => 'WARNING',
                    'VERBOSITY_VERBOSE' => 'NOTICE',
                ],
                'console_formatter_options' => [],
                'interactive_only' => false,
            ])
        ;
    }

    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void
    {
        // No specific validation
    }

    /**
     * @param array{
     *     verbosity_levels: array<int, int>,
     *     console_formatter_options: array,
     *     interactive_only: bool,
     * } $handler
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition
    {
        $definition = new Definition(ConsoleHandler::class);
        $definition->setArguments([
            null,
            $config['bubble'],
            $handler['verbosity_levels'] ?? [],
            $handler['console_formatter_options'],
            $handler['interactive_only'],
        ]);
        $definition->addTag('kernel.event_subscriber');

        return $definition;
    }
}
