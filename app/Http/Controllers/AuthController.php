<?php

namespace App\Http\Controllers;

use App\Support\LanRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * App entry. Guests always get HTTP 200 login (never redirect to /login).
     * Authenticated users go to role home — never to /login.
     */
    public function home(Request $request)
    {
        if ($this->hasUsableAuthenticatedUser($request)) {
            return LanRedirect::to(LanRedirect::homePath());
        }

        return view('auth.login');
    }

    /**
     * Login form. Guests: 200. Authenticated: role home (never "/").
     * Handled in-controller so we do not depend on guest middleware HOME="/".
     */
    public function showLogin(Request $request)
    {
        if ($this->hasUsableAuthenticatedUser($request)) {
            return LanRedirect::to(LanRedirect::homePath());
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials)) {
            // Drop stale intended targets (especially "/") before regenerating.
            $request->session()->forget('url.intended');
            $request->session()->regenerate();

            $user = Auth::user();
            if (! $user || ! $user->active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return LanRedirect::toLogin()->withErrors([
                    'username' => 'Your account is inactive.',
                ]);
            }

            // Intended was cleared; always land on role home after login.
            return LanRedirect::to(LanRedirect::homePath($user));
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return LanRedirect::toLogin();
    }

    /**
     * True only when session points at an active admin/worker.
     * Half-auth / stale cookies are wiped so they cannot bounce / ↔ /login.
     */
    private function hasUsableAuthenticatedUser(Request $request): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();
        if (! $user || ! $user->active || ! in_array($user->role, ['admin', 'worker'], true)) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return false;
        }

        return true;
    }
}
