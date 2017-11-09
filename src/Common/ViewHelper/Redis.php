<?php
namespace Common\ViewHelper;

use Predis\Client;

use Zend\View\Helper\AbstractHelper;

final class Redis extends AbstractHelper
{
    private $redis;

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    public function __invoke()
    {
        return $this->redis;
    }
}
