<?php
namespace Common\Middleware;

use Interop\Container\ContainerInterface;

class PersistMediaFromRedisFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new PersistMediaFromRedis(
            $container->get('redis')
        );
    }
}


