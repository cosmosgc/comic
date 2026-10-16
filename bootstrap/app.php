<?php

use App\Http\Middleware\TrackPageView;
use App\Http\Middleware\TransferTokenMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'track.views' => TrackPageView::class,
            'transfer.token' => TransferTokenMiddleware::class,
        ]);
        // Raw JS preference cookie (grid/list toggle) — never encrypted.
        $middleware->encryptCookies(except: ['comics_layout']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
