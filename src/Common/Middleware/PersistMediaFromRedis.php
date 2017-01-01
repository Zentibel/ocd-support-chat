<?php
namespace Common\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use Predis\Client;
use Zend\Diactoros\Response\JsonResponse;

class PersistMediaFromRedis
{
    private $redis;

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {
        $key        = $request->getAttribute('media_id_key', 'mediaId');
        $destFolder = $request->getAttribute('media_dest_path', 'default');
        $payload    = $request->getParsedBody();
        $mediaId    = $payload[$key];
        $media      = $this->redis->hgetall('media:'.$mediaId);
        $ext        =
            ($media['contentType'] == 'image/jpeg' ? 'jpg' : false) ?:
            ($media['contentType'] == 'image/png'  ? 'png' : false) ?:
            '.wtf';
        $mediaPath  = "public/uploads/{$destFolder}/{$mediaId}.{$ext}";

        file_put_contents($mediaPath, $media['data']);

        $this->redis->del('media:'.$mediaId);


        if ($key = $request->getAttribute('media_path_cqrs_key', false)) {
            $payload = $request->getAttribute('cqrs_payload', []);
            $payload[$key] = str_replace('public','',$mediaPath);
            $request = $request->withAttribute('cqrs_payload', $payload);
        }

        return $next($request, $response);
    }
}
