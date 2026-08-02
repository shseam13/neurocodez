@php($editing = $item->exists)

<x-layouts.admin :title="$editing ? $item->title : 'New case study'"
                 :heading="$editing ? 'Edit case study' : 'New case study'">

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.portfolio.index') }}" class="text-sm text-ink-muted hover:text-ink">&larr; All work</a>

        @if ($editing && $item->isPublished())
            <a href="{{ route('public.portfolio.show', $item) }}" target="_blank" rel="noopener"
               class="text-sm font-medium text-brand-text hover:underline">View live &rarr;</a>
        @endif
    </div>

    <form method="POST"
          action="{{ $editing ? route('admin.portfolio.update', $item) : route('admin.portfolio.store') }}"
          enctype="multipart/form-data"
          class="grid gap-5 lg:grid-cols-[1fr_320px]">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-5">
            <div class="surface p-6">
                <x-ui.field name="title" label="Title" required :value="old('title', $item->title)" />

                <div class="mt-5">
                    <x-ui.field name="summary" label="Summary" type="textarea"
                                :value="old('summary', $item->summary)"
                                help="One or two sentences, shown on the listing cards." />
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.field name="client_display_name" label="Client name to show publicly"
                                :value="old('client_display_name', $item->client_display_name)"
                                help="Leave blank to keep the client anonymous. Never taken from their record." />
                    <x-ui.field name="live_url" label="Live URL" :value="old('live_url', $item->live_url)" />
                    <x-ui.field name="tech" label="Built with"
                                :value="old('tech', is_array($item->tech) ? implode(', ', $item->tech) : '')"
                                help="Comma separated, e.g. Laravel, MySQL, Tailwind" />
                    <x-ui.field name="year" label="Year" type="number" :value="old('year', $item->year)" />
                </div>
            </div>

            <div class="surface overflow-hidden" data-md-editor
                 data-md-preview-url="{{ route('admin.posts.preview') }}">
                <div class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                    <button type="button" class="md-tab is-active" data-md-tab="write">Write</button>
                    <button type="button" class="md-tab" data-md-tab="preview">Preview</button>
                    <span class="ml-auto text-xs text-ink-muted">The full case study</span>
                </div>

                <div data-md-pane="write">
                    <textarea name="body_markdown" data-md-input
                              class="md-input w-full resize-y border-0 bg-transparent px-5 py-4 text-ink focus:outline-none"
                              placeholder="## The brief&#10;&#10;What the client needed.&#10;&#10;## What we built&#10;&#10;How you solved it.">{{ old('body_markdown', $item->body_markdown) }}</textarea>
                </div>

                <div data-md-pane="preview" hidden>
                    <div class="prose-neuro md-preview px-5 py-4" data-md-preview></div>
                </div>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="surface p-5">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">Publishing</h2>

                <label for="status" class="mb-1.5 block text-sm font-medium text-ink">Status</label>
                <select id="status" name="status"
                        class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none">
                    <option value="draft" @selected(old('status', $item->status ?? 'draft') === 'draft')>Draft</option>
                    <option value="published" @selected(old('status', $item->status) === 'published')>Published</option>
                </select>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <x-ui.field name="position" label="Order" type="number"
                                :value="old('position', $item->position ?? 0)" />
                </div>

                <label class="mt-4 flex items-center gap-2.5 text-sm text-ink">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $item->is_featured))
                           class="h-4 w-4 rounded border-line text-brand focus:ring-brand/40">
                    Feature on the home page
                </label>

                <button type="submit"
                        class="mt-5 w-full rounded-xl bg-brand px-6 py-2.5 font-semibold text-white transition hover:bg-brand-hover">
                    {{ $editing ? 'Save' : 'Create case study' }}
                </button>
            </div>

            <div class="surface p-5">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">Cover image</h2>

                @if ($item->coverUrl())
                    <img src="{{ $item->coverUrl() }}" alt="" class="mb-3 w-full rounded-lg border border-line">
                @endif

                <input type="file" name="cover" accept="image/*"
                       class="w-full text-sm text-ink file:mr-3 file:rounded-md file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white">
                @error('cover')<p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>@enderror

                <div class="mt-3">
                    <x-ui.field name="cover_alt" label="Alt text" :value="old('cover_alt', $item->cover_alt)" />
                </div>
            </div>

            <div class="surface p-5">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-muted">Internal link</h2>
                <label for="project_id" class="sr-only">Linked project</label>
                <select id="project_id" name="project_id"
                        class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-sm text-ink focus:border-brand focus:outline-none">
                    <option value="">Not linked</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id', $item->project_id) == $project->id)>
                            {{ $project->title }} — {{ $project->client->name }}
                        </option>
                    @endforeach
                </select>
                {{-- Stated plainly so nobody assumes linking exposes anything. --}}
                <p class="mt-1.5 text-xs text-ink-muted">
                    For your reference only. Never shown publicly, and no project data is
                    pulled onto the page.
                </p>
            </div>

            <div class="surface p-5">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">SEO</h2>
                <x-ui.field name="meta_title" label="Meta title" :value="old('meta_title', $item->meta_title)" />
                <div class="mt-4">
                    <x-ui.field name="meta_description" label="Meta description" type="textarea"
                                :value="old('meta_description', $item->meta_description)" />
                </div>
            </div>
        </aside>
    </form>

    @if ($editing)
        {{-- Gallery lives outside the main form: file uploads need their own
             submit, and one giant multipart form would re-upload everything on
             every save. --}}
        <section class="surface mt-5 overflow-hidden">
            <div class="border-b border-line px-5 py-3.5">
                <h2 class="font-semibold text-ink">Gallery</h2>
                <p class="mt-0.5 text-xs text-ink-muted">Shown below the case study, in this order.</p>
            </div>

            @forelse ($item->images as $image)
                <div class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-3 last:border-0">
                    <img src="{{ $image->url() }}" alt="" loading="lazy"
                         class="h-14 w-24 shrink-0 rounded object-cover">

                    <form method="POST" action="{{ route('admin.portfolio.images.update', [$item, $image]) }}"
                          class="flex min-w-48 flex-1 gap-2">
                        @csrf @method('PUT')
                        <input type="text" name="caption" value="{{ $image->caption }}" placeholder="Caption (optional)"
                               class="flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                        <button type="submit" class="text-xs font-medium text-brand-text hover:underline">Save</button>
                    </form>

                    <div class="flex shrink-0 items-center gap-1">
                        @foreach (['up' => '&uarr;', 'down' => '&darr;'] as $dir => $glyph)
                            <form method="POST" action="{{ route('admin.portfolio.images.move', [$item, $image]) }}">
                                @csrf
                                <input type="hidden" name="direction" value="{{ $dir }}">
                                <button type="submit"
                                        @disabled(($dir === 'up' && $loop->parent->first) || ($dir === 'down' && $loop->parent->last))
                                        class="rounded-md px-2 py-1 text-sm text-ink-muted hover:bg-surface-alt hover:text-ink disabled:opacity-30">
                                    {!! $glyph !!}
                                </button>
                            </form>
                        @endforeach

                        <form method="POST" action="{{ route('admin.portfolio.images.destroy', [$item, $image]) }}"
                              onsubmit="return confirm('Remove this image?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="ml-2 text-xs text-ink-muted hover:text-overdue">Remove</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-ink-muted">No gallery images yet.</p>
            @endforelse

            <form method="POST" action="{{ route('admin.portfolio.images.store', $item) }}"
                  enctype="multipart/form-data" class="border-t border-line bg-surface-alt px-5 py-4">
                @csrf
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Add images</p>

                <input type="file" name="images[]" multiple accept="image/*" required
                       class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink file:mr-3 file:rounded-md file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white">

                <div class="mt-3 flex items-center gap-3">
                    <p class="text-xs text-ink-muted">Up to 12 at a time, 4 MB each.</p>
                    <button type="submit"
                            class="ml-auto rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                        Upload
                    </button>
                </div>

                @error('images')<p class="mt-2 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
                @error('images.*')<p class="mt-2 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
            </form>
        </section>

        @can('delete', $item)
            <form method="POST" action="{{ route('admin.portfolio.destroy', $item) }}" class="mt-5"
                  onsubmit="return confirm('Delete this case study?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-sm font-medium text-overdue hover:underline">Delete case study</button>
            </form>
        @endcan
    @endif
</x-layouts.admin>
