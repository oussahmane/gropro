<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Relative redirects for LAN/IP HTTP access.
 * Avoids UrlGenerator turning Location into https:// when proxies spoof X-Forwarded-Proto.
 */
class LanRedirect
{
    public static function homePath(?User $user = null): string
    {
        $user ??= Auth::user();

        return match ($user?->role) {
            'admin' => '/admin',
            'worker' => '/worker',
            default => '/login',
        };
    }

    public static function to(string $path, int $status = 302): RedirectResponse
    {
        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            $path = '/login';
        }

        return new RedirectResponse($path, $status);
    }

    /**
     * Only allow same-app relative paths (blocks external / https intended URLs).
     */
    public static function safeInternalPath(?string $path, string $fallback): string
    {
        if (! is_string($path) || $path === '') {
            return $fallback;
        }

        // Reject absolute URLs (https://… open redirects / phone scheme traps).
        if (str_contains($path, '://') || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }

        if (in_array($path, ['/', '/login'], true)) {
            return $fallback;
        }

        return $path;
    }
}
