@php
    $site = config('neuro.site');
    $social = array_filter($site['social']);
@endphp

<footer class="relative mt-24 overflow-hidden border-t border-line">
    <div class="neural-pattern" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-6xl px-5 py-14">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-brand.lockup :size="40" slogan />
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-soft">
                    {{ $site['description'] }}
                </p>
            </div>

            <div>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Explore</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ([
                        'public.portfolio.index' => 'Our work',
                        'public.blog.index' => 'Blog',
                        'public.videos' => 'Videos',
                        'public.about' => 'About',
                    ] as $route => $label)
                        <li>
                            <a href="{{ route($route) }}" class="text-ink-soft transition hover:text-brand-text">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>

                {{-- Spelled out here rather than in the header, where there is
                     no room to say who it is for. Clients and partners share
                     one sign-in; each lands in their own portal. --}}
                <h2 class="mb-3 mt-6 text-xs font-semibold uppercase tracking-wider text-ink-muted">Account</h2>
                <ul class="space-y-2 text-sm">
                    <li>
                        <a href="{{ route('login') }}" class="font-medium text-brand-text hover:underline">
                            Client &amp; partner sign in
                        </a>
                    </li>
                    <li class="text-xs leading-relaxed text-ink-muted">
                        Track your project, files and invoices.
                    </li>
                </ul>
            </div>

            <div>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Get in touch</h2>
                <ul class="space-y-2 text-sm">
                    @if ($site['email'])
                        <li><a href="mailto:{{ $site['email'] }}" class="text-ink-soft transition hover:text-brand-text">{{ $site['email'] }}</a></li>
                    @endif
                    @if ($site['phone'])
                        <li><a href="tel:{{ $site['phone'] }}" class="text-ink-soft transition hover:text-brand-text">{{ $site['phone'] }}</a></li>
                    @endif
                    <li>
                        <a href="{{ route('public.contact') }}" class="font-semibold text-brand-text hover:underline">
                            Start a project &rarr;
                        </a>
                    </li>
                </ul>

                @if ($social)
                    <div class="mt-4 flex gap-3">
                        @foreach ($social as $name => $url)
                            <a href="{{ $url }}" rel="noopener noreferrer" target="_blank"
                               class="text-sm capitalize text-ink-muted transition hover:text-brand-text">
                                {{ $name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-2 border-t border-line pt-6 text-xs text-ink-muted sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
            <p>Built by Neuro Codez.</p>
        </div>
    </div>
</footer>
