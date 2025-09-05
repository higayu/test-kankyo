<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use App\Http\Middleware\Authenticate; 
use Tymon\JWTAuth\Http\Middleware\Authenticate as JWTAuthenticate;
use App\Http\Middleware\Cors;

class Kernel extends HttpKernel
{
    /**
     * ルートミドルウェア
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'jwt.auth' => JWTAuthenticate::class,
        'cors' => Cors::class,
    ];

    protected $middlewareGroups = [
        'api' => [
            \App\Http\Middleware\Cors::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
        ],
    ];
    
    protected $middleware = [
        \App\Http\Middleware\Cors::class,
    ];
}
