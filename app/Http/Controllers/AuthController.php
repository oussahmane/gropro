<?php

namespace App\Http\Controllers;

use App\Support\LanRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Entry URL for phones: guests get the login form (no redirect hop).
     */
    public function home()
    {
        if (Auth::check()) {
            return LanRedirect::to(LanRedirect::homePath());
        }

        return $this->showLogin();
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            $user = Auth::user();
            if (! $user->active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'username' => 'Your account is inactive.',
                ])->onlyInput('username');
            }

            $fallback = LanRedirect::homePath($user);
            $intended = LanRedirect::safeInternalPath(
                $request->session()->pull('url.intended'),
                $fallback,
            );

            return LanRedirect::to($intended);
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

        return LanRedirect::to('/login');
    }
}
