<x-layouts.auth title="Set your password">
    <h1 class="text-xl font-bold tracking-tight text-ink">Welcome, {{ $user->name }}</h1>
    <p class="mt-1.5 text-sm leading-relaxed text-ink-soft">
        Choose a password and your {{ strtolower($user->type->label()) }} account is ready.
    </p>

    <div class="mt-5 rounded-xl border border-line bg-surface-alt p-3">
        <p class="text-xs text-ink-muted">Signing in as</p>
        <p class="text-sm font-medium text-ink">{{ $user->email }}</p>
    </div>

    {{-- full(), not current(): current() drops the query string, taking the
         expiry and signature with it, and the `signed` middleware then rejects
         the POST with 403 Invalid signature. The signature covers the URL, not
         the HTTP method, so posting back to the same signed URL validates. --}}
    <form method="POST" action="{{ url()->full() }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-ink">
                Password <span class="text-overdue" aria-hidden="true">*</span>
            </label>
            <input id="password" name="password" type="password" required autofocus autocomplete="new-password"
                   @class([
                       'w-full rounded-xl border bg-surface px-4 py-2.5 text-ink transition focus:outline-none focus:ring-2 focus:ring-brand/40',
                       'border-overdue' => $errors->has('password'),
                       'border-line focus:border-brand' => ! $errors->has('password'),
                   ])>
            <p class="mt-1.5 text-xs text-ink-muted">At least 8 characters. Use something you don't use elsewhere.</p>
            @error('password')
                <p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-ink">
                Confirm password <span class="text-overdue" aria-hidden="true">*</span>
            </label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password"
                   class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40">
        </div>

        <button type="submit"
                class="w-full rounded-xl bg-brand px-6 py-3 font-semibold text-white transition hover:bg-brand-hover">
            Set password and continue
        </button>
    </form>
</x-layouts.auth>
