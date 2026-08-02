<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Prevents session fixation: the id the visitor arrived with must not
        // be the id that is now authenticated.
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        /*
         * Land each account type in its own application. A client following a
         * bookmarked admin URL should not be redirected there after login —
         * homeRoute() is derived from the account type, not from the intended
         * destination.
         */
        return redirect()->intended(route($user->type->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('public.home');
    }
}
