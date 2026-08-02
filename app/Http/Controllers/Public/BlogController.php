<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tag;
use App\Services\MarkdownService;
use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('public.blog.index', [
            'posts' => Post::published()->with('tags')
                ->orderByDesc('is_featured')
                ->latest('published_at')
                ->paginate(9),
            'tags' => Tag::has('posts')->orderBy('name')->get(),
            'activeTag' => null,
        ]);
    }

    public function tag(Tag $tag): View
    {
        return view('public.blog.index', [
            'posts' => $tag->posts()->published()->with('tags')
                ->latest('published_at')
                ->paginate(9),
            'tags' => Tag::has('posts')->orderBy('name')->get(),
            'activeTag' => $tag,
        ]);
    }

    public function show(Post $post, MarkdownService $markdown): View
    {
        // published() is a scope, not a global one, so route-model binding
        // would happily serve a draft to anyone guessing the slug.
        abort_unless($post->isPublished(), 404);

        $post->load('tags', 'author');
        $post->recordView();

        return view('public.blog.show', [
            'post' => $post,
            'toc' => $markdown->tableOfContents($post->body_html),
            'related' => Post::published()
                ->whereKeyNot($post->getKey())
                ->when($post->tags->isNotEmpty(), fn ($q) => $q->whereHas(
                    'tags',
                    fn ($t) => $t->whereIn('tags.id', $post->tags->pluck('id'))
                ))
                ->latest('published_at')
                ->limit(3)
                ->get(),
        ]);
    }
}
