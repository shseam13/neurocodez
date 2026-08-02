<x-layouts.admin title="Portfolio" heading="Portfolio">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-xl text-sm text-ink-soft">
            Case studies for the public site. Written separately from your project records —
            nothing here can reach a client's real name, agreed amount or commission terms.
        </p>

        @can('create', App\Models\PortfolioItem::class)
            <a href="{{ route('admin.portfolio.create') }}"
               class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                New case study
            </a>
        @endcan
    </div>

    <div class="surface overflow-hidden">
        @forelse ($items as $item)
            <div class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-3.5 last:border-0">
                <div class="h-12 w-20 shrink-0 overflow-hidden rounded bg-surface-alt">
                    @if ($item->coverUrl())
                        <img src="{{ $item->coverUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center">
                            <x-brand.mark :size="18" class="text-brand opacity-40" />
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <a href="{{ route('admin.portfolio.edit', $item) }}"
                       class="font-medium text-ink hover:text-brand-text">{{ $item->title }}</a>
                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                        @if ($item->isPublished())
                            <span class="badge badge-paid">Live</span>
                        @elseif ($item->status === 'published')
                            <span class="badge badge-due">Scheduled</span>
                        @else
                            <span class="badge badge-info">Draft</span>
                        @endif

                        @if ($item->is_featured)
                            <span class="badge badge-paid">Featured</span>
                        @endif
                        @if ($item->client_display_name)
                            <span>{{ $item->client_display_name }}</span>
                        @endif
                        @if ($item->year)<span>{{ $item->year }}</span>@endif
                        <span>{{ $item->images_count }} images</span>
                    </p>
                </div>

                <a href="{{ route('admin.portfolio.edit', $item) }}"
                   class="shrink-0 text-xs font-medium text-brand-text hover:underline">Edit</a>
            </div>
        @empty
            <div class="p-12 text-center">
                <x-brand.mark :size="40" class="mx-auto text-brand opacity-40" />
                <h2 class="mt-5 font-semibold text-ink">No case studies yet</h2>
                <p class="mt-2 text-sm text-ink-soft">
                    Writing up finished work is what convinces the next client.
                </p>
            </div>
        @endforelse
    </div>

    @if ($items->hasPages())
        <div class="mt-5">{{ $items->links() }}</div>
    @endif
</x-layouts.admin>
