<x-layouts.admin title="Videos" heading="Videos">
    @if (session('error'))
        <div class="mb-5 rounded-xl border border-overdue/40 bg-overdue/10 p-4 text-sm font-medium text-overdue">
            {{ session('error') }}
        </div>
    @endif

    <div class="surface mb-5 flex flex-wrap items-center justify-between gap-4 p-5">
        <div>
            <h2 class="font-semibold text-ink">YouTube channel</h2>
            <p class="mt-1 text-sm text-ink-soft">
                @if ($channel)
                    Syncing from <code class="text-brand-text">{{ $channel }}</code>
                    @if ($lastSync)
                        &middot; last checked {{ \Illuminate\Support\Carbon::parse($lastSync)->diffForHumans() }}
                    @endif
                @else
                    No channel configured. Set <code>NEURO_YOUTUBE_CHANNEL</code> in your .env
                @endif
            </p>
            <p class="mt-1 text-xs text-ink-muted">
                Runs hourly on its own. The feed carries roughly the latest 15 uploads —
                anything older can be added by hand below.
            </p>
        </div>

        @can('create', App\Models\Video::class)
            <form method="POST" action="{{ route('admin.videos.sync') }}">
                @csrf
                <button type="submit"
                        class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                    Sync now
                </button>
            </form>
        @endcan
    </div>

    <div class="surface overflow-hidden">
        @forelse ($videos as $video)
            <div class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-3.5 last:border-0">
                <img src="{{ $video->thumbnail() }}" alt="" loading="lazy"
                     class="h-12 w-20 shrink-0 rounded object-cover">

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-ink">{{ $video->title }}</p>
                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                        @if ($video->published_at)
                            <span>{{ $video->published_at->format('j M Y') }}</span>
                        @endif
                        @if ($video->is_manual)
                            <span class="badge badge-info">Hand-added</span>
                        @endif
                        @unless ($video->is_published)
                            <span class="badge badge-overdue">Hidden</span>
                        @endunless
                        @if ($video->is_featured)
                            <span class="badge badge-paid">Featured</span>
                        @endif
                        <a href="{{ $video->watchUrl() }}" target="_blank" rel="noopener"
                           class="text-brand-text hover:underline">Open on YouTube</a>
                    </p>
                </div>

                @can('update', $video)
                    <form method="POST" action="{{ route('admin.videos.update', $video) }}"
                          class="flex shrink-0 flex-wrap items-center gap-3 text-xs">
                        @csrf @method('PUT')
                        <input type="hidden" name="title" value="{{ $video->title }}">

                        <label class="flex items-center gap-1.5 text-ink-soft">
                            <input type="checkbox" name="is_published" value="1" @checked($video->is_published)
                                   class="h-3.5 w-3.5 rounded border-line text-brand focus:ring-brand/40">
                            Show
                        </label>
                        <label class="flex items-center gap-1.5 text-ink-soft">
                            <input type="checkbox" name="is_featured" value="1" @checked($video->is_featured)
                                   class="h-3.5 w-3.5 rounded border-line text-brand focus:ring-brand/40">
                            Feature
                        </label>

                        <button type="submit" class="font-medium text-brand-text hover:underline">Save</button>
                    </form>
                @endcan
            </div>
        @empty
            <div class="p-12 text-center">
                <x-brand.mark :size="40" class="mx-auto text-brand opacity-40" />
                <h2 class="mt-5 font-semibold text-ink">No videos yet</h2>
                <p class="mt-2 text-sm text-ink-soft">Press "Sync now" to pull them from your channel.</p>
            </div>
        @endforelse

        @can('create', App\Models\Video::class)
            <form method="POST" action="{{ route('admin.videos.store') }}"
                  class="border-t border-line bg-surface-alt px-5 py-4">
                @csrf
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Add a video by hand</p>

                <div class="flex flex-wrap gap-3">
                    <input type="text" name="url" required placeholder="YouTube URL or video id"
                           class="min-w-48 flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                    <input type="text" name="title" required placeholder="Title"
                           class="min-w-48 flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                    <button type="submit"
                            class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                        Add
                    </button>
                </div>

                @error('url')<p class="mt-2 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
            </form>
        @endcan
    </div>

    @if ($videos->hasPages())
        <div class="mt-5">{{ $videos->links() }}</div>
    @endif
</x-layouts.admin>
