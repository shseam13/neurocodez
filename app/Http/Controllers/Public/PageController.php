<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PortfolioItem;
use App\Models\Video;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{
    public function home(): View
    {
        /*
         * Deliberately NOT cached at the application layer.
         *
         * These are four small indexed queries, and the CDN cache headers on
         * this route already keep them off the origin for most visitors.
         * Caching Eloquent collections would mean serialising models into the
         * cache store, which is fragile and buys nothing here.
         */
        return view('public.home', [
            /*
             * Featured items first, then recent work fills the row.
             *
             * Showing only what is flagged featured leaves a lone card stranded
             * in a three-column grid whenever that count is not exactly 3 —
             * which is most of the time. Ordering rather than filtering keeps
             * the row full while still honouring the flag.
             */
            'work' => PortfolioItem::published()
                ->orderByDesc('is_featured')
                ->orderBy('position')
                ->limit(3)
                ->get(),
            'posts' => Post::published()->with('tags')
                ->latest('published_at')->limit(3)->get(),
            'videos' => Video::published()
                ->orderByDesc('is_featured')->orderByDesc('published_at')->limit(3)->get(),
        ]);
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function videos(): View
    {
        return view('public.videos', [
            'videos' => Video::published()
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->paginate(12),
        ]);
    }

    /** Generated rather than hand-maintained, so it cannot go stale. */
    public function sitemap(): Response
    {
        $urls = Cache::remember('public:sitemap', now()->addHour(), function () {
            $urls = [
                ['loc' => route('public.home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
                ['loc' => route('public.portfolio.index'), 'priority' => '0.9', 'changefreq' => 'weekly'],
                ['loc' => route('public.blog.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
                ['loc' => route('public.videos'), 'priority' => '0.7', 'changefreq' => 'weekly'],
                ['loc' => route('public.about'), 'priority' => '0.6', 'changefreq' => 'monthly'],
                ['loc' => route('public.contact'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ];

            foreach (Post::published()->latest('published_at')->get() as $post) {
                $urls[] = [
                    'loc' => route('public.blog.show', $post),
                    'lastmod' => $post->updated_at?->toAtomString(),
                    'priority' => '0.8',
                    'changefreq' => 'monthly',
                ];
            }

            foreach (PortfolioItem::published()->orderBy('position')->get() as $item) {
                $urls[] = [
                    'loc' => route('public.portfolio.show', $item),
                    'lastmod' => $item->updated_at?->toAtomString(),
                    'priority' => '0.8',
                    'changefreq' => 'monthly',
                ];
            }

            return $urls;
        });

        return response()
            ->view('public.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
