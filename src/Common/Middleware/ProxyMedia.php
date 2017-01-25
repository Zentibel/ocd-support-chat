<?php
namespace Common\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Stream;

class ProxyMedia
{
    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {
        if (!$mediaUrl = $request->getAttribute('mediaUrl', false)) {
            die('error-1');
        }
        if (!$mediaUrl = base64_decode($mediaUrl)) {
            die('error-2');
        }

        if (!$mediaContent = file_get_contents($mediaUrl)) {
            die('error-3');
        }

        $contentType = false;

        foreach ($http_response_header as $header) {
            if (strpos($header, 'Content-Type: image/') !== false) {
                $contentType = explode(': ', $header)[1];
                break;
            }
        }

        if (!$contentType) {
            die('error-4');
        }

        $body = new Stream('php://temp', 'wb+');
        $body->write($mediaContent);
        $body->rewind();

        return $response->withHeader('Content-Type', $contentType)
                        ->withBody($body);
    }
}

