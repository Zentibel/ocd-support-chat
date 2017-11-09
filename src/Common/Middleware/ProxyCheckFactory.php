<?php
namespace Common\Middleware;

use Interop\Container\ContainerInterface;

class ProxyCheckFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new ProxyCheck(
            $container->get('redis')
        );
    }
}
