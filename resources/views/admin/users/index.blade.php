<x-layouts.admin title="People" heading="People">
    @error('user')
        <div class="mb-5 rounded-xl border border-overdue/40 bg-overdue/10 p-4 text-sm font-medium text-overdue">
            {{ $message }}
        </div>
    @enderror

    <section class="surface mb-5 overflow-hidden">
        <div class="border-b border-line px-5 py-3.5">
            <h2 class="font-semibold text-ink">Staff</h2>
            <p class="mt-0.5 text-xs text-ink-muted">
                People who work in the admin app. You never type their password — they set their own.
            </p>
        </div>

        @foreach ($staff as $user)
            <div class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-3.5 last:border-0">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-ink">
                        {{ $user->name }}
                        @if ($user->hasPendingInvitation())
                            <span class="badge badge-due ml-1">Invitation pending</span>
                        @endif
                        @unless ($user->is_active)
                            <span class="badge badge-overdue ml-1">Deactivated</span>
                        @endunless
                    </p>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        {{ $user->email }}
                        @if ($user->last_login_at)
                            &middot; last in {{ $user->last_login_at->diffForHumans() }}
                        @endif
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex items-center gap-2">
                    @csrf @method('PUT')
                    <select name="role"
                            class="rounded-lg border border-line bg-surface px-2.5 py-1.5 text-xs text-ink focus:border-brand focus:outline-none">
                        @foreach ([App\Models\User::ROLE_ADMIN => 'Admin', App\Models\User::ROLE_SUPER_ADMIN => 'Owner'] as $value => $label)
                            <option value="{{ $value }}" @selected($user->hasRole($value))>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="text-xs font-medium text-brand-text hover:underline">Set</button>
                </form>

                <div class="flex items-center gap-3 text-xs">
                    @if ($user->hasPendingInvitation())
                        <form method="POST" action="{{ route('admin.users.resend', $user) }}">
                            @csrf
                            <button type="submit" class="text-ink-muted hover:text-ink">Resend</button>
                        </form>
                        <form method="POST" action="{{ route('admin.users.revoke', $user) }}"
                              onsubmit="return confirm('Revoke this invitation?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-ink-muted hover:text-overdue">Revoke</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.active', $user) }}">
                            @csrf @method('PUT')
                            <button type="submit" class="text-ink-muted hover:text-overdue">
                                {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach

        <form method="POST" action="{{ route('admin.users.store') }}"
              class="border-t border-line bg-surface-alt px-5 py-4">
            @csrf
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Invite a staff member</p>

            <div class="grid gap-3 sm:grid-cols-3">
                <input type="text" name="name" placeholder="Full name" required value="{{ old('name') }}"
                       class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                <input type="email" name="email" placeholder="Email" required value="{{ old('email') }}"
                       class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                <select name="role"
                        class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">
                    <option value="{{ App\Models\User::ROLE_ADMIN }}">Admin</option>
                    <option value="{{ App\Models\User::ROLE_SUPER_ADMIN }}">Owner</option>
                </select>
            </div>

            <div class="mt-3 flex items-center gap-3">
                <p class="text-xs text-ink-muted">They receive a link to set their own password.</p>
                <button type="submit"
                        class="ml-auto rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                    Send invitation
                </button>
            </div>

            @error('email')<p class="mt-2 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
        </form>
    </section>

    <section class="surface overflow-hidden">
        <div class="border-b border-line px-5 py-3.5">
            <h2 class="font-semibold text-ink">Portal accounts</h2>
            <p class="mt-0.5 text-xs text-ink-muted">
                Invite these from a client's or partner's own page, so the account is linked to the right record.
            </p>
        </div>

        @forelse ($portalUsers as $user)
            <div class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-3 last:border-0">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-ink">
                        {{ $user->name }}
                        <span class="badge badge-info ml-1">{{ $user->type->label() }}</span>
                        @if ($user->hasPendingInvitation())
                            <span class="badge badge-due ml-1">Pending</span>
                        @endif
                        @unless ($user->is_active)
                            <span class="badge badge-overdue ml-1">Deactivated</span>
                        @endunless
                    </p>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        {{ $user->email }} &middot;
                        {{ $user->client?->name ?? $user->partner?->name ?? '—' }}
                    </p>
                </div>

                <div class="flex items-center gap-3 text-xs">
                    @if ($user->hasPendingInvitation())
                        <form method="POST" action="{{ route('admin.users.resend', $user) }}">
                            @csrf
                            <button type="submit" class="text-ink-muted hover:text-ink">Resend</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.users.active', $user) }}">
                        @csrf @method('PUT')
                        <button type="submit" class="text-ink-muted hover:text-overdue">
                            {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-ink-muted">
                No client or partner logins yet.
            </p>
        @endforelse
    </section>
</x-layouts.admin>
