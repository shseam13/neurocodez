<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class InvitationController extends Controller
{
    /**
     * The invitee sets their own password.
     *
     * The `signed` middleware on the route verifies the signature and expiry, so
     * this only has to check that the invitation has not already been used.
     */
    public function show(User $user): View|RedirectResponse
    {
        if (! $user->hasPendingInvitation()) {
            return redirect()->route('login')->with('status',
                'That invitation has already been used. Sign in, or reset your password.');
        }

        return view('auth.accept-invitation', ['user' => $user]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        if (! $user->hasPendingInvitation()) {
            return redirect()->route('login')->with('status',
                'That invitation has already been used. Sign in instead.');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->string('password')),
            // Clearing this is what makes the link single-use: the signed URL
            // stays valid until it expires, but it no longer does anything.
            'invited_at' => null,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route($user->type->homeRoute())
            ->with('status', 'Welcome. Your password is set.');
    }
}
