<section class="surface overflow-hidden">
    <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
        <div>
            <h2 class="font-semibold text-ink">Files</h2>
            <p class="mt-0.5 text-xs text-ink-muted">
                Only files marked visible appear in the client's portal.
            </p>
        </div>
        <span class="text-xs text-ink-muted">{{ $project->files->count() }} stored</span>
    </div>

    @forelse ($project->files as $file)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-ink">
                    {{ $file->original_name }}
                    @if ($file->client_visible)
                        <span class="badge badge-paid ml-1">Client can see</span>
                    @else
                        <span class="badge badge-info ml-1">Internal</span>
                    @endif
                </p>
                <p class="nums mt-0.5 text-xs text-ink-muted">
                    {{ $file->humanSize() }} &middot; {{ $file->created_at->format('j M Y') }}
                    @if ($file->uploadedBy) &middot; {{ $file->uploadedBy->name }} @endif
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3 text-xs">
                <a href="{{ route('files.download', $file) }}"
                   class="font-medium text-brand-text hover:underline">Download</a>

                @can('update', $file)
                    <form method="POST" action="{{ route('admin.projects.files.visibility', [$project, $file]) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-ink-muted hover:text-ink">
                            {{ $file->client_visible ? 'Make internal' : 'Share with client' }}
                        </button>
                    </form>
                @endcan

                @can('delete', $file)
                    <form method="POST" action="{{ route('admin.projects.files.destroy', [$project, $file]) }}"
                          onsubmit="return confirm('Remove {{ addslashes($file->original_name) }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-ink-muted hover:text-overdue">Remove</button>
                    </form>
                @endcan
            </div>
        </div>
    @empty
        <p class="px-5 py-8 text-center text-sm text-ink-muted">
            No files yet. Upload deliverables and working files here so they stop living in chat threads.
        </p>
    @endforelse

    @can('create', App\Models\ProjectFile::class)
        <form method="POST" action="{{ route('admin.projects.files.store', $project) }}"
              enctype="multipart/form-data" class="border-t border-line bg-surface-alt px-5 py-4">
            @csrf
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Upload files</p>

            <input type="file" name="files[]" multiple required
                   class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink file:mr-3 file:rounded-md file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white">

            <div class="mt-3 flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-ink-soft">
                    <input type="checkbox" name="client_visible" value="1"
                           class="h-4 w-4 rounded border-line text-brand focus:ring-brand/40">
                    Share these with the client
                </label>

                <p class="text-xs text-ink-muted">Up to 10 files, 20 MB each.</p>

                <button type="submit"
                        class="ml-auto rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                    Upload
                </button>
            </div>

            @error('files')
                <p class="mt-2 text-xs font-medium text-overdue">{{ $message }}</p>
            @enderror
            @error('files.*')
                <p class="mt-2 text-xs font-medium text-overdue">{{ $message }}</p>
            @enderror
        </form>
    @endcan
</section>
