<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
            'client.active' => \App\Http\Middleware\EnsureClientIsActive::class,
            'client.company.approved' => \App\Http\Middleware\EnsureClientCompanyIsApproved::class,
            'client.portal' => \App\Http\Middleware\EnsureClientPortalAccess::class,
            'backoffice.portal' => \App\Http\Middleware\EnsureBackofficeAccess::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
