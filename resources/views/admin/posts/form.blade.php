@php($editing = $post->exists)

<x-layouts.admin :title="$editing ? $post->title : 'New post'"
                 :heading="$editing ? 'Edit post' : 'New post'">

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.posts.index') }}" class="text-sm text-ink-muted hover:text-ink">&larr; All posts</a>

        @if ($editing && $post->isPublished())
            <a href="{{ route('public.blog.show', $post) }}" target="_blank" rel="noopener"
               class="text-sm font-medium text-brand-text hover:underline">View live &rarr;</a>
        @endif
    </div>

    <form method="POST"
          action="{{ $editing ? route('admin.posts.update', $post) : route('admin.posts.store') }}"
          enctype="multipart/form-data"
          class="grid gap-5 lg:grid-cols-[1fr_320px]">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-5">
            <div class="surface p-6">
                <x-ui.field name="title" label="Title" required :value="old('title', $post->title)" />

                <div class="mt-5">
                    <x-ui.field name="excerpt" label="Excerpt" type="textarea"
                                :value="old('excerpt', $post->excerpt)"
                                help="Shown on listing cards and in search results. Left blank, it is generated from the first paragraph." />
                </div>
            </div>

            {{-- Editor. Preview is rendered by the server so it matches the
                 published page exactly. --}}
            <div class="surface overflow-hidden" data-md-editor
                 data-md-preview-url="{{ route('admin.posts.preview') }}">
                <div class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                    <button type="button" class="md-tab is-active" data-md-tab="write">Write</button>
                    <button type="button" class="md-tab" data-md-tab="preview">Preview</button>
                    <span class="ml-auto text-xs text-ink-muted" data-md-meta></span>
                </div>

                <div data-md-pane="write">
                    <textarea name="body_markdown" data-md-input
                              class="md-input w-full resize-y border-0 bg-transparent px-5 py-4 text-ink focus:outline-none"
                              placeholder="Write in Markdown.&#10;&#10;## A heading&#10;&#10;Some prose, then code:&#10;&#10;```php&#10;echo 'hello';&#10;```">{{ old('body_markdown', $post->body_markdown) }}</textarea>
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
                    <option value="draft" @selected(old('status', $post->status ?? 'draft') === 'draft')>Draft</option>
                    <option value="published" @selected(old('status', $post->status) === 'published')>Published</option>
                </select>

                <div class="mt-4">
                    <x-ui.field name="published_at" label="Publish date" type="date"
                                :value="old('published_at', $post->published_at?->toDateString())"
                                help="A future date schedules the post — it stays hidden until then." />
                </div>

                <label class="mt-4 flex items-center gap-2.5 text-sm text-ink">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured))
                           class="h-4 w-4 rounded border-line text-brand focus:ring-brand/40">
                    Feature this post
                </label>

                <button type="submit"
                        class="mt-5 w-full rounded-xl bg-brand px-6 py-2.5 font-semibold text-white transition hover:bg-brand-hover">
                    {{ $editing ? 'Save' : 'Create post' }}
                </button>
            </div>

            <div class="surface p-5">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">Tags</h2>
                <input type="text" name="tags" value="{{ old('tags', $post->tags->pluck('name')->join(', ')) }}"
                       placeholder="Laravel, CSS, Tutorial"
                       class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                <p class="mt-1.5 text-xs text-ink-muted">Comma separated. New tags are created automatically.</p>

                @if ($allTags->isNotEmpty())
                    <p class="mt-3 text-xs text-ink-muted">
                        Existing: {{ $allTags->pluck('name')->join(', ') }}
                    </p>
                @endif
            </div>

            <div class="surface p-5">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">Cover image</h2>

                @if ($post->coverUrl())
                    <img src="{{ $post->coverUrl() }}" alt="" class="mb-3 w-full rounded-lg border border-line">
                @endif

                <input type="file" name="cover" accept="image/*"
                       class="w-full text-sm text-ink file:mr-3 file:rounded-md file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white">
                @error('cover')<p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>@enderror

                <div class="mt-3">
                    <x-ui.field name="cover_alt" label="Alt text" :value="old('cover_alt', $post->cover_alt)"
                                help="Describes the image for screen readers and search engines." />
                </div>
            </div>

            <div class="surface p-5">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">SEO</h2>
                <x-ui.field name="meta_title" label="Meta title" :value="old('meta_title', $post->meta_title)" />
                <div class="mt-4">
                    <x-ui.field name="meta_description" label="Meta description" type="textarea"
                                :value="old('meta_description', $post->meta_description)" />
                </div>
            </div>
        </aside>
    </form>

    @if ($editing)
        @can('delete', $post)
            <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="mt-5"
                  onsubmit="return confirm('Delete this post? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="text-sm font-medium text-overdue hover:underline">Delete post</button>
            </form>
        @endcan
    @endif
</x-layouts.admin>
