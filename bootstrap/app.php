<?php

use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RoleMiddleware;
use App\Support\LanRedirect;
use Illuminate\Auth\AuthenticationException;
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
        // Do NOT trust all proxies on LAN/artisan serve: clients can send
        // X-Forwarded-Proto: https and Laravel then redirects to https://… which
        // phones follow into ERR_TOO_MANY_REDIRECTS. Opt in via TRUSTED_PROXIES.
        $trustedProxies = env('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))),
            );
        }

        // Guests → /login (relative via exception renderer). Auth users → role home, NEVER "/".
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn () => LanRedirect::homePath());

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'guest' => RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Relative /login for unauthenticated users. Never store "/" as url.intended.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 401);
            }

            if ($request->hasSession()) {
                $request->session()->forget('url.intended');
                $path = '/'.ltrim($request->path(), '/');
                if ($path !== '/' && $path !== '/login') {
                    $request->session()->put('url.intended', $path);
                }
            }

            return LanRedirect::toLogin();
        });
    })->create();
