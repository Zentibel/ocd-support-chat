<?php
return [
    'routes' => [
        [
            'name' => 'media-get-id',
            'path' => '/media',
            'middleware' => [
                Auth\Middleware\AssertIsAuthenticated::class,
                Common\Middleware\HandleMediaUpload::class,
            ],
            'allowed_methods' => ['GET'],
        ],
        [
            'name' => 'media-upload',
            'path' => '/media',
            'middleware' => [
                Auth\Middleware\AssertIsAuthenticated::class,
                Common\Middleware\HandleMediaUpload::class,
            ],
            'allowed_methods' => ['POST'],
        ],
        [
            'name' => 'media-upload-existing',
            'path' => '/media/{mediaId:[^/]+}',
            'middleware' => [
                Auth\Middleware\AssertIsAuthenticated::class,
                Common\Middleware\HandleMediaUpload::class,
            ],
            'allowed_methods' => ['POST'],
        ],
        [
            'name' => 'media-persist-existing',
            'path' => '/media/{mediaId:[^/]+}',
            'middleware' => [
                Auth\Middleware\AssertIsAuthenticated::class,
                Common\Middleware\PersistMediaFromRedis::class,
                Common\Middleware\ReturnJsonResponse::class,
            ],
            'allowed_methods' => ['GET'],
            'options' => [
                'defaults' => [
                    'cqrs_result' => 'success', // messy
                ],
            ],
        ],
    ],
];
