<x-layouts.admin title="Stage sets" heading="Stage sets">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-xl text-sm text-ink-soft">
            Reusable pipelines. A web build and a logo job have nothing in common stage-wise,
            so you define your own and pick one per project.
        </p>

        @can('create', App\Models\StageSet::class)
            <a href="{{ route('admin.stage-sets.create') }}"
               class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                New stage set
            </a>
        @endcan
    </div>

    @error('set')
        <div class="mb-5 rounded-xl border border-overdue/40 bg-overdue/10 p-4 text-sm font-medium text-overdue">
            {{ $message }}
        </div>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($sets as $set)
            <div class="surface flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-ink">{{ $set->name }}</h2>
                        @if ($set->description)
                            <p class="mt-1 text-xs text-ink-soft">{{ $set->description }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1">
                        @if ($set->is_default)
                            <span class="badge badge-paid">Default</span>
                        @endif
                        @unless ($set->is_active)
                            <span class="badge badge-overdue">Inactive</span>
                        @endunless
                    </div>
                </div>

                <p class="nums mt-3 text-xs text-ink-muted">
                    {{ $set->stages_count }} stages &middot; used by {{ $set->projects_count }} projects
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-3 text-xs">
                    <a href="{{ route('admin.stage-sets.edit', $set) }}"
                       class="font-medium text-brand-text hover:underline">Edit stages</a>

                    @can('create', App\Models\StageSet::class)
                        <form method="POST" action="{{ route('admin.stage-sets.duplicate', $set) }}">
                            @csrf
                            <button type="submit" class="text-ink-muted hover:text-ink">Duplicate</button>
                        </form>
                    @endcan

                    @unless ($set->is_default)
                        <form method="POST" action="{{ route('admin.stage-sets.default', $set) }}">
                            @csrf
                            <button type="submit" class="text-ink-muted hover:text-ink">Make default</button>
                        </form>
                    @endunless
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
