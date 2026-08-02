@props(['video'])

{{-- A real link to YouTube, upgraded by JS into an in-page lightbox.

     Kept as an <a> deliberately: middle-click and "open in new tab" still work,
     crawlers see a real destination, and if JS fails the card degrades to what
     it did before rather than becoming a dead div. The iframe is only created
     on click, so a grid of cards never loads a player per card. --}}
<a href="{{ $video->watchUrl() }}" target="_blank" rel="noopener noreferrer"
   data-video-id="{{ $video->youtube_id }}"
   data-video-title="{{ $video->title }}"
   class="glass group block overflow-hidden transition hover:-translate-y-0.5">
    <div class="relative aspect-video overflow-hidden bg-surface-alt">
        <img src="{{ $video->thumbnail() }}"
             alt="{{ $video->title }}"
             loading="lazy" decoding="async"
             class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">

        <span class="absolute inset-0 flex items-center justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand/90 shadow-lg transition group-hover:scale-110">
                <svg class="ml-0.5 h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M8 5v14l11-7z" />
                </svg>
            </span>
        </span>
    </div>

    <div class="p-5">
        <h3 class="line-clamp-2 font-semibold leading-snug text-ink group-hover:text-brand-text">
            {{ $video->title }}
        </h3>

        @if ($video->published_at)
            <p class="mt-2 text-xs text-ink-muted">{{ $video->published_at->format('j M Y') }}</p>
        @endif
    </div>
</a>
