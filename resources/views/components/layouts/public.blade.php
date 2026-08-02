@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => 'website',
    'canonical' => null,
])

@php
    $site = config('neuro.site');
    $pageTitle = $title ? $title.' — '.config('app.name') : config('app.name').' — '.$site['tagline'];
    $pageDescription = $description ?: $site['description'];
    $pageImage = $image ?: asset('brand/icon-512.png');
    $canonicalUrl = $canonical ?: url()->current();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- Same pre-paint theme resolution as the app, but the public site
         defaults to LIGHT: a marketing page is read in daylight by people who
         have no relationship with us yet, and light reads as more trustworthy
         for a company site. A returning visitor's saved choice still wins. --}}
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('nc-theme'); } catch (e) {}
            if (t !== 'light' && t !== 'dark') { t = 'light'; }
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Only rendered where a session exists. Cacheable public pages run
         without session middleware, and csrf_token() would throw there. --}}
    @if (request()->hasSession())
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endif

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    <meta property="og:type" content="{{ $type }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:image" content="{{ $pageImage }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#1E073A" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#F5F2FB" media="(prefers-color-scheme: light)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Organization schema, so search engines can render a knowledge panel.
         Built in PHP rather than via @json: Blade's directive argument parser
         chokes on a multi-line nested array. --}}
    @php
        $organizationSchema = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('app.name'),
            'slogan' => $site['tagline'],
            'url' => url('/'),
            'logo' => asset('brand/icon-512.png'),
            'description' => $site['description'],
            'sameAs' => array_values(array_filter($site['social'])),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @endphp
    <script type="application/ld+json">{!! $organizationSchema !!}</script>

    {{ $head ?? '' }}
</head>
<body class="min-h-dvh bg-bg-base">
    <div class="orb-field" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>
    <div class="grain" aria-hidden="true"></div>

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    <x-public.header />

    <main id="main">
        {{ $slot }}
    </main>

    <x-public.footer />
</body>
</html>
