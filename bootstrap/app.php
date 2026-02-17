<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Role-based access for protected admin/instructor APIs.
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            // Database query logging (only active in debug mode)
            'query.log' => \App\Http\Middleware\LogDatabaseQueries::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Add input sanitization, request logging, and compression for all API routes
        $middleware->api(append: [
            \App\Http\Middleware\SanitizeInput::class,
            \App\Http\Middleware\LogRequests::class,
            \App\Http\Middleware\CompressResponse::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
