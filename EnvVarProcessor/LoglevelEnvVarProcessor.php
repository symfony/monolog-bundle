<?php


namespace Symfony\Bundle\MonologBundle\EnvVarProcessor;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

class LoglevelEnvVarProcessor implements EnvVarProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function getEnv($prefix, $name, \Closure $getEnv)
    {
        if ('loglevel' !== $prefix) {
            throw new RuntimeException(sprintf('Unsupported env var prefix "%s".', $prefix));
        }

        $level = $getEnv($name);

        $levelConstant = 'Monolog\Logger::'.strtoupper($level);
        if (!defined($levelConstant)) {
            throw new RuntimeException(sprintf('The configured log level "%s" in environment variable "%s" is invalid as it is not defined in Monolog\Logger.', $level, $name));
        }

        return constant($levelConstant);
    }

    /**
     * {@inheritdoc}
     */
    public static function getProvidedTypes()
    {
        return array(
            'loglevel' => 'int',
        );
    }
}