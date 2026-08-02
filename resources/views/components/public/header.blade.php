@php
    $links = [
        ['label' => 'Work', 'route' => 'public.portfolio.index'],
        ['label' => 'Blog', 'route' => 'public.blog.index'],
        ['label' => 'Videos', 'route' => 'public.videos'],
        ['label' => 'About', 'route' => 'public.about'],
    ];
@endphp

<header class="sticky top-0 z-40">
    <div class="glass-flat border-x-0 border-t-0">
        <nav class="mx-auto flex max-w-6xl items-center gap-4 px-5 py-3" aria-label="Main">
            <a href="{{ route('public.home') }}" class="flex items-center gap-2.5 shrink-0">
                <x-brand.mark :size="32" class="text-brand" />
                <span class="hidden text-lg font-bold tracking-tight text-ink sm:block">NEURO&#8209;CODEZ</span>
            </a>

            <div class="ml-auto hidden items-center gap-1 md:flex">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}"
                       @class([
                           'rounded-lg px-3 py-2 text-sm font-medium transition',
                           'text-brand-text' => request()->routeIs($link['route'].'*'),
                           'text-ink-soft hover:text-ink' => ! request()->routeIs($link['route'].'*'),
                       ])>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="ml-auto flex items-center gap-2 md:ml-0">
                <x-ui.theme-toggle />

                {{-- Always reads "Sign in", never "Dashboard".

                     These pages are cached by the CDN with no session, so the
                     markup MUST be identical for every visitor — swapping this
                     label based on auth state would let the CDN serve one
                     person's version to everyone. /login redirects onward if
                     you already have a session. --}}
                <a href="{{ route('login') }}"
                   class="hidden rounded-lg px-3 py-2 text-sm font-medium text-ink-soft transition hover:text-ink sm:block">
                    Sign in
                </a>

                <a href="{{ route('public.contact') }}"
                   class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                    Hire us
                </a>
            </div>
        </nav>

        {{-- Mobile nav. A plain scrollable row rather than a hamburger: four
             links do not justify hiding navigation behind a tap. --}}
        <div class="flex items-center gap-1 overflow-x-auto px-5 pb-2 md:hidden">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   @class([
                       'whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium',
                       'text-brand-text' => request()->routeIs($link['route'].'*'),
                       'text-ink-soft' => ! request()->routeIs($link['route'].'*'),
                   ])>
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ route('login') }}"
               class="ml-auto whitespace-nowrap rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-soft">
                Sign in
            </a>
        </div>
    </div>
</header>
