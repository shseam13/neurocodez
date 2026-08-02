@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      @if ($serverTheme = auth()->user()?->theme_pref) data-theme="{{ $serverTheme }}" @endif>
<head>
    {{--
        This must stay the first executable thing in <head>. Resolving the theme
        after paint means every load flashes the wrong colours, and on a dark
        default that flash is a white strobe.

        Order: the signed-in user's saved preference (already rendered onto
        <html> above) -> localStorage -> the OS setting.
    --}}
    <script>
        (function () {
            var el = document.documentElement;
            var server = el.getAttribute('data-theme');
            if (server) {
                try { localStorage.setItem('nc-theme', server); } catch (e) {}
                return;
            }
            var t = null;
            try { t = localStorage.getItem('nc-theme'); } catch (e) {}
            if (t !== 'light' && t !== 'dark') {
                t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
            }
            el.setAttribute('data-theme', t);
        })();
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title . ' — ' . config('app.name') : config('app.name') }}</title>

    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
    {{-- iOS ignores the manifest's display mode; these two are what give it a
         full-screen home-screen app rather than a Safari tab. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Neuro Codez">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('brand/logo-mark-purple-128.png') }}" type="image/png">

    {{-- Matches --bg-base per theme so the mobile browser chrome blends in. --}}
    <meta name="theme-color" content="#1E073A" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#F5F2FB" media="(prefers-color-scheme: light)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased">
    {{-- The background the glass actually refracts. Fixed, so it never scrolls
         and never repaints. --}}
    <div class="orb-field" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>
    <div class="grain" aria-hidden="true"></div>

    {{ $slot }}

    @livewireScripts
</body>
</html>
