<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PortfolioItem;
use Illuminate\Contracts\View\View;

class PortfolioController extends Controller
{
    public function index(): View
    {
        return view('public.portfolio.index', [
            'items' => PortfolioItem::published()
                ->orderByDesc('is_featured')
                ->orderBy('position')
                ->paginate(12),
        ]);
    }

    public function show(PortfolioItem $portfolioItem): View
    {
        abort_unless($portfolioItem->isPublished(), 404);

        /*
         * Only `images` is loaded. The `project` relation is deliberately NOT
         * eager-loaded and must never be referenced in the view — it points at
         * the internal record holding the client's real name, the agreed
         * amount and the partner's commission terms.
         */
        $portfolioItem->load('images');

        return view('public.portfolio.show', [
            'item' => $portfolioItem,
            'more' => PortfolioItem::published()
                ->whereKeyNot($portfolioItem->getKey())
                ->orderBy('position')
                ->limit(3)
                ->get(),
        ]);
    }
}
