@props(['post'])

<article class="glass group flex flex-col overflow-hidden transition hover:-translate-y-0.5">
    <a href="{{ route('public.blog.show', $post) }}" class="block">
        <div class="aspect-[16/10] overflow-hidden bg-surface-alt">
            @if ($post->coverUrl())
                <img src="{{ $post->coverUrl() }}"
                     alt="{{ $post->cover_alt ?: $post->title }}"
                     loading="lazy" decoding="async"
                     class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
            @else
                <div class="flex h-full w-full items-center justify-center">
                    <x-brand.mark :size="40" class="text-brand opacity-30" />
                </div>
            @endif
        </div>
    </a>

    <div class="flex flex-1 flex-col p-5">
        @if ($post->tags->isNotEmpty())
            <div class="mb-2 flex flex-wrap gap-1.5">
                @foreach ($post->tags->take(2) as $tag)
                    <a href="{{ route('public.blog.tag', $tag) }}"
                       class="rounded-md bg-surface-alt px-2 py-0.5 text-xs text-brand-text hover:underline">
                        {{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif

        <h3 class="font-semibold leading-snug text-ink">
            <a href="{{ route('public.blog.show', $post) }}" class="group-hover:text-brand-text">
                {{ $post->title }}
            </a>
        </h3>

        @if ($post->excerpt)
            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-ink-soft">{{ $post->excerpt }}</p>
        @endif

        <p class="mt-4 flex items-center gap-2 text-xs text-ink-muted">
            @if ($post->published_at)
                <time datetime="{{ $post->published_at->toDateString() }}">
                    {{ $post->published_at->format('j M Y') }}
                </time>
                <span aria-hidden="true">&middot;</span>
            @endif
            <span>{{ $post->reading_minutes }} min read</span>
        </p>
    </div>
</article>
