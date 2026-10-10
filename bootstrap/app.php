<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
        then: function () {
            // Mail Sequencer web routes live in their own file so the legacy routes stay untouched.
            Route::middleware('web')->group(base_path('routes/sequencer.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Guests hitting a protected page go to the login form; signed-in users
        // hitting /login or /signup go to the dashboard.
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');

        // Behind nginx / a load balancer: trust X-Forwarded-* so URLs, HTTPS detection and
        // rate limiting see the real client. Set TRUSTED_PROXIES to the proxy IPs ("*" = all).
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // One-click unsubscribe (RFC 8058) is a POST made by the mail client, which has no CSRF token.
        // The unguessable per-contact token is the credential.
        $middleware->validateCsrfTokens(except: ['unsubscribe/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The REST API always answers JSON, including for auth failures.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
