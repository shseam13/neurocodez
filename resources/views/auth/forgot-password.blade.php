<x-layouts.auth title="Reset password">
    <h1 class="text-xl font-bold tracking-tight text-ink">Forgot your password?</h1>
    <p class="mt-1.5 text-sm leading-relaxed text-ink-soft">
        Enter your email and we'll send you a link to set a new one.
    </p>

    @if (session('status'))
        <div class="mt-5 rounded-xl border border-paid/40 bg-paid/10 p-3 text-sm font-medium text-paid">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf

        <x-ui.field name="email" label="Email" type="email" required
                    :value="old('email')" autocomplete="username" autofocus />

        <button type="submit"
                class="w-full rounded-xl bg-brand px-6 py-3 font-semibold text-white transition hover:bg-brand-hover">
            Send reset link
        </button>
    </form>

    <p class="mt-5 text-center text-sm text-ink-muted">
        <a href="{{ route('login') }}" class="font-medium text-brand-text hover:underline">Back to sign in</a>
    </p>
</x-layouts.auth>
