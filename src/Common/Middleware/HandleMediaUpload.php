<?php
namespace Common\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use Predis\Client;
use Zend\Diactoros\Response\JsonResponse;

class HandleMediaUpload
{
    private $redis;

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {
        $mediaId     = Uuid::uuid4()->toString();
        $contentType = $request->getHeader('Content-Type')[0];
        $data        = $request->getBody()->getContents();

        $rKey = 'media:'.$mediaId;
        $this->redis->hset($rKey, 'contentType', $contentType);
        $this->redis->hset($rKey, 'data', $data);
        $this->redis->expire($rKey, 60);

        return new JsonResponse(['mediaId' => $mediaId]);
    }
}

