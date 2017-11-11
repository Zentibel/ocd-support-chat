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
                if ($ip != '209.2.21.146') {
                    $this->redis->sAdd('proxy-ips', $ip);
                }
            } else {
                $this->redis->sAdd('clean-ips', $ip);
            }
        }

        $ipoc = explode('.', $ip);
        if (($ipoc[0] == '113' && $ipoc[1] == '199') || ($ipoc[0] == '202' && $ipoc[1] == '70')) {
            $this->redis->sRem('clean-ips', $ip);
            $this->redis->sAdd('proxy-ips', $ip);
        }
        return $next($request, $response);
    }
}
