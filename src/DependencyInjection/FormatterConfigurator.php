<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;

/**
 * Configures formatter options on handlers.
 *
 * @author Pascal Cescon <pascal.cescon@gmail.com>
 *
 * @internal
 */
final class FormatterConfigurator
{
    public function __construct(
        private bool $includeStacktraces = false,
        private ?string $basePath = null,
    ) {
    }

    public function __invoke(HandlerInterface $handler): void
    {
        $formatter = $handler->getFormatter();

        if ($this->includeStacktraces && ($formatter instanceof LineFormatter || $formatter instanceof JsonFormatter)) {
            $formatter->includeStacktraces();
        }

        if (null !== $this->basePath && $formatter instanceof LineFormatter) {
            $formatter->setBasePath($this->basePath);
        }
    }
}
