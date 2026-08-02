<x-layouts.public
    title="Videos"
    description="Free coding and technology tutorials from the Neuro Codez YouTube channel.">

    <section class="relative overflow-hidden border-b border-line">
        <div class="neural-pattern neural-pattern-fade" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-6xl px-5 py-16">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-5xl">Videos</h1>
            <p class="mt-4 max-w-xl text-lg text-ink-soft">
                Free tutorials on coding and technology, straight from our YouTube channel.
            </p>

            @if ($channel = config('neuro.site.social.youtube'))
                <a href="{{ $channel }}" target="_blank" rel="noopener noreferrer"
                   class="mt-7 inline-block rounded-xl bg-brand px-6 py-3 font-semibold text-white transition hover:bg-brand-hover">
                    Subscribe on YouTube
                </a>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-14">
        @if ($videos->isEmpty())
            <x-public.empty-state
                heading="No videos yet"
                body="Once the channel is connected, the latest uploads will appear here automatically." />
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($videos as $video)
                    <x-public.video-card :video="$video" />
                @endforeach
            </div>

            <div class="mt-10">{{ $videos->links() }}</div>
        @endif
    </section>
</x-layouts.public>
