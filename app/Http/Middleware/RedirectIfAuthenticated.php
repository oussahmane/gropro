<?php

namespace App\Http\Middleware;

use App\Support\LanRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relative redirect for authenticated users hitting guest routes.
 * Avoids https:// Location when Host/X-Forwarded-* are untrusted.
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
                return LanRedirect::to(LanRedirect::homePath());
            }
        }

        return $next($request);
    }
}
