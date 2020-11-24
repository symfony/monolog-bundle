<?php


namespace Symfony\Bundle\MonologBundle\Services;


use Monolog\Handler\HandlerInterface;

class HandlerManager
{

    /**
     * @var HandlerInterface[]
     */
    private $handlers;

    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    public function close()
    {
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }
}
