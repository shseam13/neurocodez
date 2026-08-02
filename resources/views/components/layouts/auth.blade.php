@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('nc-theme'); } catch (e) {}
            if (t !== 'light' && t !== 'dark') { t = 'dark'; }
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <meta name="theme-color" content="#1E073A" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#F5F2FB" media="(prefers-color-scheme: light)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh">
    <div class="orb-field" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>
    <div class="grain" aria-hidden="true"></div>

    <div class="flex min-h-dvh flex-col items-center justify-center px-5 py-10">
        <a href="{{ route('public.home') }}" class="mb-8">
            <x-brand.lockup :size="46" slogan />
        </a>

        <main class="glass relative w-full max-w-md overflow-hidden p-8">
            <div class="arc-rings"></div>

            <div class="relative">
                {{ $slot }}
            </div>
        </main>

        <div class="mt-6 flex items-center gap-4 text-sm text-ink-muted">
            <a href="{{ route('public.home') }}" class="transition hover:text-brand-text">&larr; Back to site</a>
            <x-ui.theme-toggle />
        </div>
    </div>
</body>
</html>
