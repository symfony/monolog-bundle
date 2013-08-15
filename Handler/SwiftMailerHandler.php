<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Handler;

use Monolog\Handler\SwiftMailerHandler as BaseSwiftMailerHandler;

/**
 * Extended SwiftMailerHandler that flushes mail queue if necessary
 *
 * @author Philipp Kräutli <pkraeutli@astina.ch>
 */
class SwiftMailerHandler extends BaseSwiftMailerHandler
{
    protected $transport;

    /**
     * @param \Swift_Transport $transport
     */
    public function setTransport(\Swift_Transport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * {@inheritdoc}
     */
    protected function send($content, array $records)
    {
        parent::send($content, $records);

        $this->flushQueue();
    }

    /**
     * Flushes the mail queue if necessary
     */
    private function flushQueue()
    {
        $transport = $this->mailer->getTransport();
        if (!$transport instanceof \Swift_Transport_SpoolTransport) {
            return;
        }

        $spool = $transport->getSpool();
        if (!$spool instanceof \Swift_MemorySpool) {
            return;
        }

        if (null == $this->transport) {
            throw new \Exception('No transport available to flush mail queue');
        }

        $spool->flushQueue($this->transport);
    }
}
