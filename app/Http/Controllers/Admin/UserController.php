<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

class UserController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function index(): View
    {
        Gate::authorize('manageUsers');

        return view('admin.users.index', [
            'staff' => User::query()->staff()->with('roles')->orderBy('name')->get(),
            'portalUsers' => User::query()
                ->whereIn('type', ['client', 'partner'])
                ->with('client', 'partner')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageUsers');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])],
        ]);

        try {
            $user = $this->invitations->inviteStaff(
                $data['name'], $data['email'], $data['role'], $request->user()
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return back()->with('status', "Invitation sent to {$user->email}.");
    }

    public function resend(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageUsers');

        try {
            $this->invitations->resend($user, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return back()->with('status', "Invitation re-sent to {$user->email}.");
    }

    public function revoke(User $user): RedirectResponse
    {
        Gate::authorize('manageUsers');

        try {
            $this->invitations->revoke($user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return back()->with('status', 'Invitation revoked.');
    }

    /** Deactivate or reactivate. Never a hard delete — history references users. */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageUsers');

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        /*
         * The guard that stops you locking yourself out of your own company.
         *
         * Without it, deactivating the only remaining super admin leaves nobody
         * who can manage users — recoverable only by editing the database.
         */
        if ($user->is_active && $user->isLastSuperAdmin()) {
            return back()->withErrors([
                'user' => 'This is the only active owner account. Promote someone else first.',
            ]);
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('status', $user->is_active
            ? "{$user->name} reactivated."
            : "{$user->name} deactivated and signed out everywhere.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageUsers');

        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])],
        ]);

        abort_unless($user->isStaff(), 404, 'Roles apply to staff accounts only.');

        // Same lockout guard: demotion is as dangerous as deactivation.
        if ($data['role'] !== User::ROLE_SUPER_ADMIN && $user->isLastSuperAdmin()) {
            return back()->withErrors([
                'user' => 'This is the only owner account. Promote someone else before demoting them.',
            ]);
        }

        $user->syncRoles([$data['role']]);

        return back()->with('status', "{$user->name} is now {$data['role']}.");
    }
}
