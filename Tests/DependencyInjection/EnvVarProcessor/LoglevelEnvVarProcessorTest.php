<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Tests\EnvVarProcessor;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\EnvVarProcessor\LoglevelEnvVarProcessor;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

class LoglevelEnvVarProcessorTest extends TestCase
{
    public function testValidEnvValue()
    {
        if (!interface_exists('Symfony\Component\DependencyInjection\EnvVarProcessorInterface')) {
            return;
        }

        $loglevelEnvVarProcessor = new LoglevelEnvVarProcessor();
        $result = $loglevelEnvVarProcessor->getEnv('loglevel', 'LOGLEVEL', function($varName) {
            if ('LOGLEVEL' === $varName) {
                return 'debug';
            }

            return 'invalid-value';
        });

        $this->assertEquals(100, $result);

        $result = $loglevelEnvVarProcessor->getEnv('loglevel', 'LOGLEVEL', function($varName) {
            if ('LOGLEVEL' === $varName) {
                return 'ERROR';
            }

            return 'invalid-value';
        });

        $this->assertEquals(400, $result);
    }

    public function testInvalidPrefix()
    {
        if (!interface_exists('Symfony\Component\DependencyInjection\EnvVarProcessorInterface')) {
            return;
        }
        $this->expectException('\Symfony\Component\DependencyInjection\Exception\RuntimeException');

        $loglevelEnvVarProcessor = new LoglevelEnvVarProcessor();
        $loglevelEnvVarProcessor->getEnv('invalidPrefix', 'LOGLEVEL', function($varName) { });
    }

    public function testInvalidValue()
    {
        if (!interface_exists('Symfony\Component\DependencyInjection\EnvVarProcessorInterface')) {
            return;
        }
        $this->expectException('\Symfony\Component\DependencyInjection\Exception\RuntimeException');

        $loglevelEnvVarProcessor = new LoglevelEnvVarProcessor();
        $loglevelEnvVarProcessor->getEnv('loglevel', 'LOGLEVEL', function($varName) {
            return 'invalid-value';
        });
    }

    public function testInvalidVariable()
    {
        if (!interface_exists('Symfony\Component\DependencyInjection\EnvVarProcessorInterface')) {
            return;
        }
        $this->expectException('\Symfony\Component\DependencyInjection\Exception\RuntimeException');

        $loglevelEnvVarProcessor = new LoglevelEnvVarProcessor();
        $loglevelEnvVarProcessor->getEnv('loglevel', 'LOGLEVEL', function($varName) {
            throw new RuntimeException('test');
        });
    }
}