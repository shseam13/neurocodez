<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tag;
use App\Services\MarkdownService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Post::class);

        $search = $request->string('q')->trim()->toString();

        return view('admin.posts.index', [
            'posts' => Post::query()
                ->with('tags')
                ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
                ->orderByRaw("FIELD(status, 'draft', 'published')")
                ->latest('updated_at')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Post::class);

        return view('admin.posts.form', [
            'post' => new Post(['status' => 'draft']),
            'allTags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, MarkdownService $markdown): RedirectResponse
    {
        Gate::authorize('create', Post::class);

        $post = new Post($this->validated($request));
        $post->author_id = $request->user()->id;
        $this->applyCover($request, $post);

        // Fall back to a generated excerpt so listing cards and meta
        // descriptions are never blank.
        if (blank($post->excerpt)) {
            $post->excerpt = $markdown->excerpt($post->body_markdown);
        }

        $post->save();
        $this->syncTags($request, $post);

        return redirect()
            ->route('admin.posts.edit', $post)
            ->with('status', 'Post created.');
    }

    public function edit(Post $post): View
    {
        Gate::authorize('update', $post);

        return view('admin.posts.form', [
            'post' => $post->load('tags'),
            'allTags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Post $post, MarkdownService $markdown): RedirectResponse
    {
        Gate::authorize('update', $post);

        $post->fill($this->validated($request));
        $this->applyCover($request, $post);

        if (blank($post->excerpt)) {
            $post->excerpt = $markdown->excerpt($post->body_markdown);
        }

        $post->save();
        $this->syncTags($request, $post);

        return back()->with('status', $post->isPublished() ? 'Post saved and live.' : 'Draft saved.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $title = $post->title;
        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', "\"{$title}\" deleted.");
    }

    /**
     * Server-side preview.
     *
     * Rendered by the same MarkdownService the published page uses, so what the
     * author sees is exactly what visitors get — a client-side library would
     * drift from it on code blocks, sanitising and heading anchors.
     */
    public function preview(Request $request, MarkdownService $markdown): JsonResponse
    {
        Gate::authorize('create', Post::class);

        $body = (string) $request->input('body_markdown', '');

        return response()->json([
            'html' => $markdown->toHtml($body),
            'reading_minutes' => $markdown->readingMinutes($body),
        ]);
    }

    private function applyCover(Request $request, Post $post): void
    {
        if (! $request->hasFile('cover')) {
            return;
        }

        $disk = config('filesystems.default');
        $old = $post->cover_path;

        $post->cover_disk = $disk;
        $post->cover_path = $request->file('cover')->store('posts', $disk);

        // Replacing a cover should not leave the previous file behind forever.
        if ($old) {
            Storage::disk($post->getOriginal('cover_disk') ?? $disk)->delete($old);
        }
    }

    private function syncTags(Request $request, Post $post): void
    {
        $names = collect(explode(',', (string) $request->input('tags')))
            ->map(fn (string $n) => trim($n))
            ->filter()
            ->unique();

        $post->tags()->sync($names->map(fn (string $n) => Tag::findOrCreateByName($n)->id));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body_markdown' => ['nullable', 'string', 'max:200000'],
            'cover' => ['nullable', 'image', 'max:4096'],
            'cover_alt' => ['nullable', 'string', 'max:190'],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'is_featured' => ['nullable', 'boolean'],
        ]) + ['is_featured' => $request->boolean('is_featured')];
    }
}
