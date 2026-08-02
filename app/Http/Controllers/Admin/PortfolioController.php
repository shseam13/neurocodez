<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortfolioItem;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PortfolioController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PortfolioItem::class);

        return view('admin.portfolio.index', [
            'items' => PortfolioItem::query()
                ->withCount('images')
                ->orderByRaw("FIELD(status, 'draft', 'published')")
                ->orderBy('position')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', PortfolioItem::class);

        $item = new PortfolioItem(['status' => 'draft', 'year' => (int) now()->year]);

        // Allows "write this up" straight from a delivered project.
        if ($project = Project::find($request->integer('project'))) {
            $item->project_id = $project->id;
            $item->title = $project->title;
            $item->year = $project->delivered_at?->year ?? $project->created_at->year;
        }

        return view('admin.portfolio.form', $this->formData($item));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', PortfolioItem::class);

        $item = new PortfolioItem($this->validated($request));
        $this->applyCover($request, $item);
        $item->save();

        return redirect()
            ->route('admin.portfolio.edit', $item)
            ->with('status', 'Case study created. Add images below.');
    }

    public function edit(PortfolioItem $portfolioItem): View
    {
        Gate::authorize('update', $portfolioItem);

        return view('admin.portfolio.form', $this->formData($portfolioItem->load('images')));
    }

    public function update(Request $request, PortfolioItem $portfolioItem): RedirectResponse
    {
        Gate::authorize('update', $portfolioItem);

        $portfolioItem->fill($this->validated($request));
        $this->applyCover($request, $portfolioItem);
        $portfolioItem->save();

        return back()->with('status', $portfolioItem->isPublished() ? 'Saved and live.' : 'Draft saved.');
    }

    public function destroy(PortfolioItem $portfolioItem): RedirectResponse
    {
        Gate::authorize('delete', $portfolioItem);

        $title = $portfolioItem->title;
        $portfolioItem->delete();

        return redirect()->route('admin.portfolio.index')->with('status', "\"{$title}\" deleted.");
    }

    private function formData(PortfolioItem $item): array
    {
        return [
            'item' => $item,
            /*
             * Linking to a project is an internal convenience only — it is what
             * lets you jump from a case study back to the real job. Nothing on
             * the public side may traverse it, because that record holds the
             * client's real name, the agreed amount and commission terms.
             */
            'projects' => Project::query()
                ->with('client')
                ->latest()
                ->limit(100)
                ->get(),
        ];
    }

    private function applyCover(Request $request, PortfolioItem $item): void
    {
        if (! $request->hasFile('cover')) {
            return;
        }

        $disk = config('filesystems.default');
        $old = $item->cover_path;
        $oldDisk = $item->cover_disk ?? $disk;

        $item->cover_disk = $disk;
        $item->cover_path = $request->file('cover')->store('portfolio', $disk);

        if ($old) {
            Storage::disk($oldDisk)->delete($old);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body_markdown' => ['nullable', 'string', 'max:200000'],
            // What the PUBLIC is told the client is called — which may be
            // nothing at all. Never read from clients.name.
            'client_display_name' => ['nullable', 'string', 'max:190'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'cover' => ['nullable', 'image', 'max:4096'],
            'cover_alt' => ['nullable', 'string', 'max:190'],
            'tech' => ['nullable', 'string', 'max:500'],
            'live_url' => ['nullable', 'url', 'max:500'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_featured' => ['nullable', 'boolean'],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ]);

        // Stored as a json array so the public cards can render chips.
        $data['tech'] = collect(explode(',', (string) ($data['tech'] ?? '')))
            ->map(fn (string $t) => trim($t))
            ->filter()
            ->values()
            ->all();

        $data['is_featured'] = $request->boolean('is_featured');
        $data['position'] = $data['position'] ?? 0;

        return $data;
    }
}
