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

use Symfony\Bundle\MonologBundle\EnvVarProcessor\LoglevelEnvVarProcessor;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

class LoglevelEnvVarProcessorTest extends \PHPUnit_Framework_TestCase
{
    public function testValidEnvValue()
    {
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

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     */
    public function testInvalidPrefix()
    {
        $loglevelEnvVarProcessor = new LoglevelEnvVarProcessor();
        $loglevelEnvVarProcessor->getEnv('invalidPrefix', 'LOGLEVEL', function($varName) { });
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     */
    public function testInvalidValue()
    {
        $loglevelEnvVarProcessor = new LoglevelEnvVarProcessor();
        $loglevelEnvVarProcessor->getEnv('loglevel', 'LOGLEVEL', function($varName) {
            return 'invalid-value';
        });
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     */
    public function testInvalidVariable()
    {
        $loglevelEnvVarProcessor = new LoglevelEnvVarProcessor();
        $loglevelEnvVarProcessor->getEnv('loglevel', 'LOGLEVEL', function($varName) {
            throw new RuntimeException('test');
        });
    }
}