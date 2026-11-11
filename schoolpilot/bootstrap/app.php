<?php

use App\Http\Middleware\UseSchoolDatabase;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');

        // Trust the Codespace proxy.
        $middleware->trustProxies(at: '*');

        // Set the school database after the session starts but before route auth.
        $middleware->web(append: [UseSchoolDatabase::class]);

        // Tenant resolution must run after StartSession but before auth middleware
        // asks the school guard's user provider to load a tenant user.
        $middleware->prependToPriorityList(
            \\Illuminate\\Auth\\Middleware\\Authenticate::class,
            UseSchoolDatabase::class,
        );

        // API routes use their own authentication mechanism.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
