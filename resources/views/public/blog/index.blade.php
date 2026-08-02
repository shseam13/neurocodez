<x-layouts.public
    :title="$activeTag ? $activeTag->name.' posts' : 'Blog'"
    description="Tutorials and notes on building software, from the Neuro Codez team.">

    <section class="relative overflow-hidden border-b border-line">
        <div class="neural-pattern neural-pattern-fade" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-6xl px-5 py-16">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-5xl">
                {{ $activeTag ? $activeTag->name : 'Blog' }}
            </h1>
            <p class="mt-4 max-w-xl text-lg text-ink-soft">
                @if ($activeTag)
                    Posts tagged &ldquo;{{ $activeTag->name }}&rdquo;.
                @else
                    Tutorials and notes on building software.
                @endif
            </p>

            @if ($tags->isNotEmpty())
                <div class="mt-7 flex flex-wrap gap-2">
                    <a href="{{ route('public.blog.index') }}"
                       @class([
                           'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                           'bg-brand text-white' => ! $activeTag,
                           'glass-flat text-ink-soft hover:text-ink' => (bool) $activeTag,
                       ])>All</a>

                    @foreach ($tags as $tag)
                        <a href="{{ route('public.blog.tag', $tag) }}"
                           @class([
                               'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                               'bg-brand text-white' => $activeTag?->is($tag),
                               'glass-flat text-ink-soft hover:text-ink' => ! $activeTag?->is($tag),
                           ])>{{ $tag->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-14">
        @if ($posts->isEmpty())
            <x-public.empty-state
                heading="No posts yet"
                body="Tutorials and write-ups will appear here soon." />
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-public.post-card :post="$post" />
                @endforeach
            </div>

            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </section>
</x-layouts.public>
