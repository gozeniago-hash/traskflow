<?php

use App\Http\Middleware\EnsureAccountSecure;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureMember;
use App\Http\Middleware\EnsureWorkspace;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UseNearestForwardedIp;
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
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'workspace' => EnsureWorkspace::class,
            'member' => EnsureMember::class,
            'client' => EnsureClient::class,
            'admin' => EnsureAdmin::class,
            'secure' => EnsureAccountSecure::class,
        ]);
        // Most hosts terminate HTTPS at a proxy.
        $middleware->trustProxies(at: '*');
        // …but read the visitor's address from the last hop only (see the middleware).
        $middleware->prepend(UseNearestForwardedIp::class);
        $middleware->append(SecurityHeaders::class);
        // Every page and live action counts against the per-person limit (config taskflow.requests_per_minute).
        $middleware->web(append: ['throttle:web']);
        $middleware->validateCsrfTokens(except: ['github/webhook/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
