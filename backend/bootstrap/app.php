<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SPA Sanctum : cookies de session sur les routes API
        $middleware->statefulApi();

        $middleware->append(\App\Http\Middleware\EntetesSecurite::class);

        $middleware->alias([
            'permission' => \App\Http\Middleware\VerifierPermission::class,
            'mdp.force' => \App\Http\Middleware\ForcerChangementMdp::class,
            'session.limites' => \App\Http\Middleware\LimitesDeSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
