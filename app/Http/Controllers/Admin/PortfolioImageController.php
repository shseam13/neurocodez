<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortfolioImage;
use App\Models\PortfolioItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PortfolioImageController extends Controller
{
    public function store(Request $request, PortfolioItem $portfolioItem): RedirectResponse
    {
        Gate::authorize('update', $portfolioItem);

        $request->validate([
            'images' => ['required', 'array', 'max:12'],
            'images.*' => ['required', 'image', 'max:4096'],
        ]);

        $disk = config('filesystems.default');
        $position = (int) ($portfolioItem->images()->max('position') ?? -1);

        foreach ($request->file('images') as $upload) {
            $portfolioItem->images()->create([
                'disk' => $disk,
                'path' => $upload->store("portfolio/{$portfolioItem->id}", $disk),
                'position' => ++$position,
            ]);
        }

        return back()->with('status', 'Images added.');
    }

    public function update(Request $request, PortfolioItem $portfolioItem, PortfolioImage $image): RedirectResponse
    {
        Gate::authorize('update', $portfolioItem);
        abort_unless($image->portfolio_item_id === $portfolioItem->id, 404);

        $image->update($request->validate([
            'caption' => ['nullable', 'string', 'max:190'],
        ]));

        return back()->with('status', 'Caption saved.');
    }

    public function move(Request $request, PortfolioItem $portfolioItem, PortfolioImage $image): RedirectResponse
    {
        Gate::authorize('update', $portfolioItem);
        abort_unless($image->portfolio_item_id === $portfolioItem->id, 404);

        $ordered = $portfolioItem->images()->get();
        $index = $ordered->search(fn (PortfolioImage $i) => $i->id === $image->id);
        $target = $request->string('direction')->toString() === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= $ordered->count()) {
            return back();
        }

        // Swap, then renumber the whole gallery so positions stay contiguous.
        $ordered[$index] = $ordered[$target];
        $ordered[$target] = $image;

        foreach ($ordered->values() as $i => $item) {
            $item->forceFill(['position' => $i])->save();
        }

        return back();
    }

    public function destroy(PortfolioItem $portfolioItem, PortfolioImage $image): RedirectResponse
    {
        Gate::authorize('update', $portfolioItem);
        abort_unless($image->portfolio_item_id === $portfolioItem->id, 404);

        // PortfolioImage has no soft deletes, and its deleted hook removes the
        // stored object — a gallery image has no audit value to preserve.
        $image->delete();

        return back()->with('status', 'Image removed.');
    }
}
