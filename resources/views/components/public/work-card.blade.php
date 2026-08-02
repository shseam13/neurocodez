@props(['item'])

<a href="{{ route('public.portfolio.show', $item) }}"
   class="glass group block overflow-hidden transition hover:-translate-y-0.5">
    <div class="aspect-[16/10] overflow-hidden bg-surface-alt">
        @if ($item->coverUrl())
            <img src="{{ $item->coverUrl() }}"
                 alt="{{ $item->cover_alt ?: $item->title }}"
                 loading="lazy" decoding="async"
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @else
            {{-- Placeholder rather than a broken frame: the mark on the brand
                 canvas still looks deliberate. --}}
            <div class="flex h-full w-full items-center justify-center">
                <x-brand.mark :size="48" class="text-brand opacity-40" />
            </div>
        @endif
    </div>

    <div class="p-5">
        <h3 class="font-semibold leading-snug text-ink group-hover:text-brand-text">{{ $item->title }}</h3>

        @if ($item->summary)
            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-ink-soft">{{ $item->summary }}</p>
        @endif

        @if ($item->tech)
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach (array_slice($item->tech, 0, 4) as $tech)
                    <span class="rounded-md bg-surface-alt px-2 py-0.5 text-xs text-ink-muted">{{ $tech }}</span>
                @endforeach
            </div>
        @endif
    </div>
</a>
