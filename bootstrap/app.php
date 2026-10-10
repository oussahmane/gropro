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
        // LAN / artisan serve: trust NO proxies by default (blocks spoofed https:// redirects).
        // Opt in only behind a real reverse proxy: TRUSTED_PROXIES=127.0.0.1
        $trustedProxies = env('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))),
            );
        } else {
            $middleware->trustProxies(at: []);
        }

        // Auth users → role home only (never "/"). Guests → /login.
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
