<x-layouts.admin title="Account" heading="Account">
    <div class="max-w-xl space-y-5">
        <section class="glass rounded-2xl p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-muted">Signed in as</h2>

            <p class="mt-2 font-medium text-ink">{{ $user->name }}</p>
            <p class="text-sm text-ink-soft">{{ $user->email }}</p>
            <p class="mt-1 text-xs text-ink-muted">
                {{ $user->isSuperAdmin() ? 'Owner' : $user->type->label() }}
            </p>
        </section>

        <section class="glass rounded-2xl p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-muted">Change password</h2>

            <form method="POST" action="{{ route('account.password') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                {{-- Password managers need the account this form belongs to.
                     Hidden and non-editable: the email itself is not changed here. --}}
                <input type="hidden" name="email" value="{{ $user->email }}" autocomplete="username">

                <div>
                    <label for="current_password" class="mb-1.5 block text-sm font-medium text-ink">
                        Current password <span class="text-overdue" aria-hidden="true">*</span>
                    </label>
                    <input id="current_password" name="current_password" type="password" required
                           autocomplete="current-password"
                           @class([
                               'w-full rounded-xl border bg-surface px-4 py-2.5 text-ink transition focus:outline-none focus:ring-2 focus:ring-brand/40',
                               'border-overdue' => $errors->has('current_password'),
                               'border-line focus:border-brand' => ! $errors->has('current_password'),
                           ])>
                    @error('current_password')
                        <p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-ink">
                        New password <span class="text-overdue" aria-hidden="true">*</span>
                    </label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           @class([
                               'w-full rounded-xl border bg-surface px-4 py-2.5 text-ink transition focus:outline-none focus:ring-2 focus:ring-brand/40',
                               'border-overdue' => $errors->has('password'),
                               'border-line focus:border-brand' => ! $errors->has('password'),
                           ])>
                    <p class="mt-1.5 text-xs text-ink-muted">At least 8 characters.</p>
                    @error('password')
                        <p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-ink">
                        Confirm new password <span class="text-overdue" aria-hidden="true">*</span>
                    </label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           autocomplete="new-password"
                           class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40">
                </div>

                <p class="text-xs text-ink-muted">
                    Any other device signed in to this account will be signed out.
                </p>

                <button type="submit"
                        class="rounded-xl bg-brand px-6 py-3 font-semibold text-white transition hover:bg-brand-hover">
                    Update password
                </button>
            </form>
        </section>
    </div>
</x-layouts.admin>
