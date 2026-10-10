<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Relative redirects for LAN/IP HTTP access.
 *
 * Hard rules to kill cookie bounce loops:
 * - Authenticated users never redirect to "/" or "/login"
 * - "/" is never a redirect target (guest login is rendered there with 200)
 */
class LanRedirect
{
    /**
     * Role dashboard only. Never returns "/".
     */
    public static function homePath(?User $user = null): string
    {
        $user ??= Auth::user();

        return match ($user?->role) {
            'worker' => '/worker',
            default => '/admin',
        };
    }

    /**
     * Relative redirect. Refuses "/" so / ↔ /login cannot form.
     * Authenticated users are never sent to "/login" via this helper.
     * Use {@see toLogin()} after logout.
     */
    public static function to(string $path, int $status = 302): RedirectResponse
    {
        if ($path === '' || $path === '/' || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '://')) {
            $path = Auth::check() ? self::homePath() : '/login';
        }

        if ($path === '/' || ($path === '/login' && Auth::check())) {
            $path = self::homePath();
        }

        return new RedirectResponse($path, $status);
    }

    /**
     * Intentional guest destination after logout / inactive account.
     */
    public static function toLogin(int $status = 302): RedirectResponse
    {
        return new RedirectResponse('/login', $status);
    }

    /**
     * Only allow same-app relative paths. "/" and "/login" always fall back
     * so url.intended cannot recreate the bounce.
     */
    public static function safeInternalPath(?string $path, string $fallback): string
    {
        $fallback = self::normalizeDestination($fallback);

        if (! is_string($path) || $path === '') {
            return $fallback;
        }

        if (str_contains($path, '://') || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }

        if (in_array($path, ['/', '/login'], true)) {
            return $fallback;
        }

        return $path;
    }

    private static function normalizeDestination(string $path): string
    {
        if (in_array($path, ['', '/', '/login'], true)) {
            return self::homePath();
        }

        return $path;
    }
}
