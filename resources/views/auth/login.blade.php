<x-layouts.auth title="Sign in">
    <h1 class="text-xl font-bold tracking-tight text-ink">Sign in</h1>
    <p class="mt-1.5 text-sm text-ink-soft">Welcome back. Pick up where you left off.</p>

    @if (session('status'))
        <div class="mt-5 rounded-xl border border-paid/40 bg-paid/10 p-3 text-sm font-medium text-paid">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf

        <x-ui.field name="email" label="Email" type="email" required
                    :value="old('email')" autocomplete="username" autofocus />

        <div>
            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                <label for="password" class="text-sm font-medium text-ink">
                    Password <span class="text-overdue" aria-hidden="true">*</span>
                </label>
                <a href="{{ route('password.request') }}"
                   class="text-xs font-medium text-brand-text hover:underline">Forgot?</a>
            </div>

            <input id="password" name="password" type="password" required autocomplete="current-password"
                   @class([
                       'w-full rounded-xl border bg-surface px-4 py-2.5 text-ink transition focus:outline-none focus:ring-2 focus:ring-brand/40',
                       'border-overdue' => $errors->has('password'),
                       'border-line focus:border-brand' => ! $errors->has('password'),
                   ])>

            @error('password')
                <p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2.5 text-sm text-ink-soft">
            <input type="checkbox" name="remember" value="1"
                   class="h-4 w-4 rounded border-line text-brand focus:ring-brand/40">
            Keep me signed in
        </label>

        <button type="submit"
                class="w-full rounded-xl bg-brand px-6 py-3 font-semibold text-white transition hover:bg-brand-hover">
            Sign in
        </button>
    </form>
</x-layouts.auth>
