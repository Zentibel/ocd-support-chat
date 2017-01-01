<?php
namespace Common\Middleware;

use Interop\Container\ContainerInterface;

class HandleMediaUploadFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new HandleMediaUpload(
            $container->get('redis')
        );
    }
}


