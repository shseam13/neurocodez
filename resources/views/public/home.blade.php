<x-layouts.public>
    {{-- Hero -------------------------------------------------------------- --}}
    <section class="relative overflow-hidden">
        <div class="neural-pattern neural-pattern-fade" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-6xl px-5 py-20 sm:py-28">
            <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-brand-text">
                {{ config('neuro.site.tagline') }}
            </p>

            <h1 class="max-w-3xl text-4xl font-bold leading-[1.1] tracking-tight text-ink sm:text-6xl">
                We build software that <span class="text-brand-text">works</span> —
                and teach how it's done.
            </h1>

            <p class="mt-6 max-w-xl text-lg leading-relaxed text-ink-soft">
                Websites, web applications and brand identities for businesses in Bangladesh
                and beyond. Plus free tutorials on our YouTube channel.
            </p>

            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ route('public.contact') }}"
                   class="rounded-xl bg-brand px-6 py-3 font-semibold text-white transition hover:bg-brand-hover">
                    Start a project
                </a>
                <a href="{{ route('public.portfolio.index') }}"
                   class="glass rounded-xl px-6 py-3 font-semibold text-ink transition hover:text-brand-text">
                    See our work
                </a>
            </div>
        </div>
    </section>

    {{-- Selected work ------------------------------------------------------ --}}
    @if ($work->isNotEmpty())
        <section class="mx-auto max-w-6xl px-5 py-16">
            <div class="mb-8 flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Selected work</h2>
                    <p class="mt-2 text-ink-soft">Projects we've designed, built and shipped.</p>
                </div>
                <a href="{{ route('public.portfolio.index') }}"
                   class="hidden shrink-0 text-sm font-semibold text-brand-text hover:underline sm:block">
                    All work &rarr;
                </a>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($work as $item)
                    <x-public.work-card :item="$item" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Videos ------------------------------------------------------------ --}}
    @if ($videos->isNotEmpty())
        <section class="relative overflow-hidden border-y border-line">
            <div class="neural-pattern" aria-hidden="true"></div>

            <div class="relative mx-auto max-w-6xl px-5 py-16">
                <div class="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">From our channel</h2>
                        <p class="mt-2 text-ink-soft">Free tutorials on coding and technology.</p>
                    </div>
                    <a href="{{ route('public.videos') }}"
                       class="hidden shrink-0 text-sm font-semibold text-brand-text hover:underline sm:block">
                        All videos &rarr;
                    </a>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($videos as $video)
                        <x-public.video-card :video="$video" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Blog -------------------------------------------------------------- --}}
    @if ($posts->isNotEmpty())
        <section class="mx-auto max-w-6xl px-5 py-16">
            <div class="mb-8 flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Latest writing</h2>
                    <p class="mt-2 text-ink-soft">Notes on building software.</p>
                </div>
                <a href="{{ route('public.blog.index') }}"
                   class="hidden shrink-0 text-sm font-semibold text-brand-text hover:underline sm:block">
                    All posts &rarr;
                </a>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-public.post-card :post="$post" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- CTA ---------------------------------------------------------------- --}}
    <section class="mx-auto max-w-6xl px-5 pb-8">
        <div class="glass relative overflow-hidden p-10 text-center sm:p-14">
            <div class="arc-rings"></div>
            <h2 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                Have a project in mind?
            </h2>
            <p class="mx-auto mt-3 max-w-lg text-ink-soft">
                Tell us what you need. We'll come back with a clear scope, a fixed price
                and a delivery date.
            </p>
            <a href="{{ route('public.contact') }}"
               class="mt-7 inline-block rounded-xl bg-brand px-7 py-3 font-semibold text-white transition hover:bg-brand-hover">
                Get in touch
            </a>
        </div>
    </section>
</x-layouts.public>
