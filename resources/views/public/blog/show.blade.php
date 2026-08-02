<x-layouts.public
    :title="$post->seoTitle()"
    :description="$post->seoDescription()"
    :image="$post->coverUrl()"
    type="article">

    <x-slot:head>
        @php
            $articleSchema = json_encode(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $post->title,
                'description' => $post->seoDescription(),
                'image' => $post->coverUrl(),
                'datePublished' => $post->published_at?->toAtomString(),
                'dateModified' => $post->updated_at?->toAtomString(),
                'author' => ['@type' => 'Organization', 'name' => config('app.name')],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => config('app.name'),
                    'logo' => ['@type' => 'ImageObject', 'url' => asset('brand/icon-512.png')],
                ],
                'mainEntityOfPage' => route('public.blog.show', $post),
            ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        @endphp
        <script type="application/ld+json">{!! $articleSchema !!}</script>
    </x-slot:head>

    <article>
        <header class="relative overflow-hidden border-b border-line">
            <div class="neural-pattern neural-pattern-fade" aria-hidden="true"></div>

            <div class="relative mx-auto max-w-3xl px-5 py-14">
                <a href="{{ route('public.blog.index') }}"
                   class="text-sm font-medium text-brand-text hover:underline">&larr; All posts</a>

                @if ($post->tags->isNotEmpty())
                    <div class="mt-6 flex flex-wrap gap-1.5">
                        @foreach ($post->tags as $tag)
                            <a href="{{ route('public.blog.tag', $tag) }}"
                               class="rounded-md bg-surface-alt px-2 py-0.5 text-xs font-medium text-brand-text hover:underline">
                                {{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                <h1 class="mt-4 text-3xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
                    {{ $post->title }}
                </h1>

                <p class="mt-5 flex flex-wrap items-center gap-2 text-sm text-ink-muted">
                    @if ($post->published_at)
                        <time datetime="{{ $post->published_at->toDateString() }}">
                            {{ $post->published_at->format('j F Y') }}
                        </time>
                        <span aria-hidden="true">&middot;</span>
                    @endif
                    <span>{{ $post->reading_minutes }} min read</span>
                </p>
            </div>
        </header>

        @if ($post->coverUrl())
            <div class="mx-auto max-w-4xl px-5 pt-10">
                <img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt ?: $post->title }}"
                     class="w-full rounded-2xl border border-line" loading="eager" decoding="async">
            </div>
        @endif

        <div class="mx-auto grid max-w-6xl gap-10 px-5 py-12 lg:grid-cols-[1fr_220px]">
            {{-- body_html was rendered and sanitised on save (html_input: strip),
                 so this is safe to print unescaped. --}}
            <div class="prose-neuro mx-auto w-full max-w-3xl">
                {!! $post->body_html !!}
            </div>

            @if (count($toc) > 1)
                <aside class="hidden lg:block">
                    <div class="sticky top-28">
                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            On this page
                        </h2>
                        <ul class="space-y-2 border-l border-line text-sm">
                            @foreach ($toc as $heading)
                                <li @class(['pl-4' => $heading['level'] === 2, 'pl-7' => $heading['level'] === 3])>
                                    <a href="#{{ $heading['id'] }}"
                                       class="text-ink-soft transition hover:text-brand-text">{{ $heading['text'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </aside>
            @endif
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-6xl border-t border-line px-5 py-14">
            <h2 class="mb-8 text-2xl font-bold tracking-tight text-ink">Keep reading</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    <x-public.post-card :post="$item" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.public>
