<x-layouts.auth title="Set a new password">
    <h1 class="text-xl font-bold tracking-tight text-ink">Set a new password</h1>
    <p class="mt-1.5 text-sm text-ink-soft">Choose something you don't use anywhere else.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-ui.field name="email" label="Email" type="email" required
                    :value="old('email', $email)" autocomplete="username" />

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

        <button type="submit"
                class="w-full rounded-xl bg-brand px-6 py-3 font-semibold text-white transition hover:bg-brand-hover">
            Update password
        </button>
    </form>
</x-layouts.auth>
