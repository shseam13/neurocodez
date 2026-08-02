{{--
    Everything rendered here is authored for publication. The item's `project`
    relation is intentionally never touched — it points at the internal record
    holding the client's real name, the agreed amount and commission terms.
--}}
<x-layouts.public
    :title="$item->seoTitle()"
    :description="$item->seoDescription()"
    :image="$item->coverUrl()"
    type="article">

    <article>
        <header class="relative overflow-hidden border-b border-line">
            <div class="neural-pattern neural-pattern-fade" aria-hidden="true"></div>

            <div class="relative mx-auto max-w-4xl px-5 py-14">
                <a href="{{ route('public.portfolio.index') }}"
                   class="text-sm font-medium text-brand-text hover:underline">&larr; All work</a>

                <h1 class="mt-6 text-3xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
                    {{ $item->title }}
                </h1>

                @if ($item->summary)
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink-soft">{{ $item->summary }}</p>
                @endif

                <dl class="mt-8 flex flex-wrap gap-x-10 gap-y-4 text-sm">
                    @if ($item->client_display_name)
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-ink-muted">Client</dt>
                            <dd class="mt-1 font-medium text-ink">{{ $item->client_display_name }}</dd>
                        </div>
                    @endif

                    @if ($item->year)
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-ink-muted">Year</dt>
                            <dd class="mt-1 font-medium text-ink">{{ $item->year }}</dd>
                        </div>
                    @endif

                    @if ($item->tech)
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-ink-muted">Built with</dt>
                            <dd class="mt-1 font-medium text-ink">{{ implode(' · ', $item->tech) }}</dd>
                        </div>
                    @endif

                    @if ($item->live_url)
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-ink-muted">Live</dt>
                            <dd class="mt-1">
                                <a href="{{ $item->live_url }}" target="_blank" rel="noopener noreferrer"
                                   class="font-medium text-brand-text hover:underline">Visit site &rarr;</a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </header>

        @if ($item->coverUrl())
            <div class="mx-auto max-w-5xl px-5 pt-10">
                <img src="{{ $item->coverUrl() }}" alt="{{ $item->cover_alt ?: $item->title }}"
                     class="w-full rounded-2xl border border-line" decoding="async">
            </div>
        @endif

        @if ($item->body_html)
            <div class="mx-auto max-w-3xl px-5 py-12">
                <div class="prose-neuro">{!! $item->body_html !!}</div>
            </div>
        @endif

        @if ($item->images->isNotEmpty())
            <div class="mx-auto max-w-5xl space-y-6 px-5 pb-12">
                @foreach ($item->images as $image)
                    <figure>
                        <img src="{{ $image->url() }}" alt="{{ $image->caption ?: $item->title }}"
                             loading="lazy" decoding="async"
                             class="w-full rounded-2xl border border-line">
                        @if ($image->caption)
                            <figcaption class="mt-2 text-center text-sm text-ink-muted">{{ $image->caption }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        @endif
    </article>

    @if ($more->isNotEmpty())
        <section class="mx-auto max-w-6xl border-t border-line px-5 py-14">
            <h2 class="mb-8 text-2xl font-bold tracking-tight text-ink">More work</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($more as $other)
                    <x-public.work-card :item="$other" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.public>
