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

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

interface HandlerExtensionInterface
{
    /**
     * Returns the name of the handler and configuration subtree.
     */
    public function getName(): string;

    /**
     * Add properties for the handler type.
     */
    public function buildArrayNode(ArrayNodeDefinition $handlerNode): void;

    /**
     * Add validates on the handler node, referencing the final "type" and the
     * type-specific subtree added in {@see buildArrayNode()}.
     */
    public function buildHandlerValidates(ArrayNodeDefinition $handlerNode): void;

    /**
     * @param array{
     *     level: string|int,
     *     bubble: bool,
     *     formatter: string|null,
     * } $config Generic options
     * @param array{} $handler Specific handler options
     */
    public function getDefinition(HandlerContext $context, array $config, array $handler): Definition;
}
