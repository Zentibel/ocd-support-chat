<?php
namespace Common\ViewHelper;

use Interop\Container\ContainerInterface;

class RedisFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new Redis(
            $container->get('redis')
        );
    }
}
