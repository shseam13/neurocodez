<x-layouts.admin title="Posts" heading="Blog posts">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.posts.index') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search titles"
                   class="w-64 max-w-full rounded-xl border border-line bg-surface px-4 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
            <button type="submit" class="glass-flat rounded-xl px-4 py-2 text-sm font-medium text-ink">Search</button>
        </form>

        @can('create', App\Models\Post::class)
            <a href="{{ route('admin.posts.create') }}"
               class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                Write a post
            </a>
        @endcan
    </div>

    <div class="surface overflow-hidden">
        @if ($posts->isEmpty())
            <div class="p-12 text-center">
                <x-brand.mark :size="40" class="mx-auto text-brand opacity-40" />
                <h2 class="mt-5 font-semibold text-ink">No posts yet</h2>
                <p class="mt-2 text-sm text-ink-soft">
                    Tutorials bring people to the site — and to your channel.
                </p>
            </div>
        @else
            @foreach ($posts as $post)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-0">
                    <div class="min-w-0">
                        <a href="{{ route('admin.posts.edit', $post) }}"
                           class="font-medium text-ink hover:text-brand-text">{{ $post->title }}</a>

                        <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                            @if ($post->status === 'published')
                                @if ($post->isPublished())
                                    <span class="badge badge-paid">Live</span>
                                @else
                                    {{-- Published but future-dated: the listing scope hides it
                                         until the date passes, so say so rather than showing
                                         "Live" for something nobody can see. --}}
                                    <span class="badge badge-due">Scheduled</span>
                                @endif
                            @else
                                <span class="badge badge-info">Draft</span>
                            @endif

                            @if ($post->published_at)
                                <span>{{ $post->published_at->format('j M Y') }}</span>
                            @endif
                            <span>{{ $post->reading_minutes }} min</span>
                            @if ($post->views > 0)
                                <span class="nums">{{ number_format($post->views) }} views</span>
                            @endif
                            @foreach ($post->tags as $tag)
                                <span class="rounded bg-surface-alt px-1.5 py-0.5">{{ $tag->name }}</span>
                            @endforeach
                        </p>
                    </div>

                    <a href="{{ route('admin.posts.edit', $post) }}"
                       class="shrink-0 text-xs font-medium text-brand-text hover:underline">Edit</a>
                </div>
            @endforeach
        @endif
    </div>

    @if ($posts->hasPages())
        <div class="mt-5">{{ $posts->links() }}</div>
    @endif
</x-layouts.admin>
