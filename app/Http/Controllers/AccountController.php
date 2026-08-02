<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

/**
 * The signed-in user's own account.
 *
 * Deliberately outside the account-type groups: staff, clients and partners all
 * need to change their own password, and the rules are identical for all three.
 */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account.edit', ['user' => auth()->user()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // `current_password` checks against the authenticated user's hash.
            // Without it, anyone who found an unattended signed-in browser
            // could lock the real owner out of their own company.
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'current_password.current_password' => 'That is not your current password.',
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        $signedOut = $this->forgetOtherSessions($user->id);

        return redirect()
            ->route('account.edit')
            ->with('status', $signedOut
                ? 'Password updated, and any other device signed in to this account has been signed out.'
                : 'Password updated.');
    }

    /**
     * Drop every stored session for this account except the current one.
     *
     * Changing a password is what you do when you suspect someone else has it.
     * If their session survives, the change has achieved nothing — they stay
     * signed in on their own machine indefinitely.
     *
     * Laravel's Auth::logoutOtherDevices() is the usual route, but it only
     * works with the AuthenticateSession middleware enabled globally, which
     * makes any test using actingAs() with two different users fail. Deleting
     * the rows directly is narrower and has no effect outside this action.
     *
     * Returns false when sessions are not in the database and there is nothing
     * to delete, so the confirmation message never claims more than it did.
     * Production and the cPanel runbook both use the database driver.
     */
    private function forgetOtherSessions(int $userId): bool
    {
        if (config('session.driver') !== 'database') {
            return false;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where('id', '!=', session()->getId())
            ->delete();

        return true;
    }
}
