<?php
namespace Common\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Predis\Client;
use Common\CheckIp;

class ProxyCheck
{
    private $redis;

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!$this->redis->sIsMember('proxy-ips', $ip) && !$this->redis->sIsMember('clean-ips', $ip)) {
            if (CheckIp::isAnon()) {
                $this->redis->sAdd('proxy-ips', $ip);
            } else {
                $this->redis->sAdd('clean-ips', $ip);
            }
        }
        return $next($request, $response);
    }
}
