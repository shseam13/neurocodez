@props(['title' => null, 'heading' => null])

@php
    $user = auth()->user();

    // Nav is derived from account type, so a portal user cannot be handed an
    // admin link even if this layout were reused by mistake.
    $nav = match ($user->type) {
        \App\Enums\AccountType::Staff => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid'],
            ['label' => 'Clients', 'route' => 'admin.clients.index', 'icon' => 'users'],
            ['label' => 'Projects', 'route' => 'admin.projects.index', 'icon' => 'folder'],
            ['label' => 'Partners', 'route' => 'admin.partners.index', 'icon' => 'share'],
            ['label' => 'Invoices', 'route' => 'admin.invoices.index', 'icon' => 'doc'],
            ['label' => 'Payouts', 'route' => 'admin.payouts.index', 'icon' => 'cash'],
            ['label' => 'Posts', 'route' => 'admin.posts.index', 'icon' => 'pen'],
            ['label' => 'Portfolio', 'route' => 'admin.portfolio.index', 'icon' => 'star'],
            ['label' => 'Videos', 'route' => 'admin.videos.index', 'icon' => 'play'],
            ['label' => 'Leads', 'route' => 'admin.leads.index', 'icon' => 'inbox'],
            ['label' => 'People', 'route' => 'admin.users.index', 'icon' => 'users'],
            ['label' => 'Stage sets', 'route' => 'admin.stage-sets.index', 'icon' => 'list'],
        ],
        \App\Enums\AccountType::Client => [
            ['label' => 'My projects', 'route' => 'portal.client.dashboard', 'icon' => 'folder'],
        ],
        \App\Enums\AccountType::Partner => [
            ['label' => 'My partner', 'route' => 'portal.partner.dashboard', 'icon' => 'share'],
        ],
    };

    // Links are dropped rather than fatal when a route does not exist yet, so
    // the shell stays usable while sections are still being built.
    $nav = array_values(array_filter($nav, fn ($i) => \Illuminate\Support\Facades\Route::has($i['route'])));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      @if ($user->theme_pref) data-theme="{{ $user->theme_pref }}" @endif>
<head>
    <script>
        (function () {
            var el = document.documentElement;
            if (el.getAttribute('data-theme')) {
                try { localStorage.setItem('nc-theme', el.getAttribute('data-theme')); } catch (e) {}
                return;
            }
            var t = null;
            try { t = localStorage.getItem('nc-theme'); } catch (e) {}
            el.setAttribute('data-theme', t === 'light' ? 'light' : 'dark');
        })();
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
    {{-- iOS ignores the manifest's display mode; these two are what give it a
         full-screen home-screen app rather than a Safari tab. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Neuro Codez">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <meta name="theme-color" content="#1E073A" media="(prefers-color-scheme: dark)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh" data-authenticated="1">
    <div class="orb-field" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>
    <div class="grain" aria-hidden="true"></div>

    <div class="flex min-h-dvh">
        {{-- Sidebar: glass, because it is chrome. Data below is on solid
             surfaces — never blur behind a table. --}}
        <aside class="glass-flat sticky top-0 hidden h-dvh w-60 shrink-0 flex-col border-y-0 border-l-0 p-4 lg:flex">
            <a href="{{ route($user->type->homeRoute()) }}" class="mb-8 flex items-center gap-2.5 px-2">
                <x-brand.mark :size="30" class="text-brand" />
                <span class="font-bold tracking-tight text-ink">NEURO&#8209;CODEZ</span>
            </a>

            <nav class="flex-1 space-y-1" aria-label="Main">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'block rounded-lg px-3 py-2 text-sm font-medium transition',
                           'bg-brand text-white' => request()->routeIs($item['route'].'*'),
                           'text-ink-soft hover:bg-surface-alt hover:text-ink' => ! request()->routeIs($item['route'].'*'),
                       ])>{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="mt-4 border-t border-line pt-4">
                <p class="px-3 text-sm font-medium text-ink">{{ $user->name }}</p>
                <p class="px-3 text-xs text-ink-muted">
                    {{ $user->isSuperAdmin() ? 'Owner' : $user->type->label() }}
                </p>

                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-lg px-3 py-2 text-left text-sm text-ink-soft transition hover:bg-surface-alt hover:text-overdue">
                        Sign out
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="glass-flat sticky top-0 z-30 border-x-0 border-t-0">
                <div class="flex items-center gap-4 px-5 py-3">
                    <a href="{{ route($user->type->homeRoute()) }}" class="lg:hidden">
                        <x-brand.mark :size="28" class="text-brand" />
                    </a>

                    <h1 class="truncate font-semibold text-ink">{{ $heading ?? $title }}</h1>

                    <div class="ml-auto flex items-center gap-2">
                        <x-ui.theme-toggle />
                    </div>
                </div>

                {{-- Mobile nav --}}
                <div class="flex gap-1 overflow-x-auto px-5 pb-2 lg:hidden">
                    @foreach ($nav as $item)
                        <a href="{{ route($item['route']) }}"
                           @class([
                               'whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium',
                               'bg-brand text-white' => request()->routeIs($item['route'].'*'),
                               'text-ink-soft' => ! request()->routeIs($item['route'].'*'),
                           ])>{{ $item['label'] }}</a>
                    @endforeach

                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                        @csrf
                        <button type="submit" class="whitespace-nowrap rounded-lg px-3 py-1.5 text-sm text-ink-muted">
                            Sign out
                        </button>
                    </form>
                </div>
            </header>

            <main class="flex-1 p-5">
                @if (session('status'))
                    <div class="mb-5 rounded-xl border border-paid/40 bg-paid/10 p-4 text-sm font-medium text-paid">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
