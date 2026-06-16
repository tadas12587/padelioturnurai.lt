<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// APP_BASE_PATH override is needed on shared hosting where the SSH shell is
// chrooted: dirname(__DIR__) resolves to /private/laravel in CLI, but the web
// server needs the full /var/www/... path baked into cached config files.
return Application::configure(basePath: getenv('APP_BASE_PATH') ?: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'setlocale' => \App\Http\Middleware\SetLocale::class,
        ]);

        // The snapshot ingest endpoint is an API-style POST from the external
        // bridge (no session/CSRF token); it is protected by a secret token.
        $middleware->validateCsrfTokens(except: [
            'overlay/ingest',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
