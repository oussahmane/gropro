<?php

namespace App\Http\Middleware;

use App\Support\LanRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticated users on guest routes → role home (never "/").
 */
class RedirectIfAuthenticated
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = $guards === [] ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                if ($user && $user->active && in_array($user->role, ['admin', 'worker'], true)) {
                    return LanRedirect::to(LanRedirect::homePath($user));
                }

                Auth::guard($guard)->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }
        }

        return $next($request);
    }
}
