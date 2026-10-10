<?php

namespace App\Http\Middleware;

use App\Support\LanRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user() || ! $request->user()->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return LanRedirect::to('/login')
                ->withErrors(['username' => 'Your account is inactive.']);
        }

        // Wrong role: 403 (not a redirect) — avoids /admin ↔ /worker ping-pong.
        if ($request->user()->role !== $role) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
