<?php
namespace Chat\Finder;

use Interop\Container\ContainerInterface;
use Auth\AuthService;

final class MessageFinderFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new MessageFinder(
            $container->get('redis'),
            $container->get(AuthService::class)
        );
    }
}
