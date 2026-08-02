<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

class VideoController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Video::class);

        return view('admin.videos.index', [
            'videos' => Video::query()
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->paginate(30),
            'channel' => config('neuro.youtube.channel'),
            'lastSync' => Video::max('synced_at'),
        ]);
    }

    /** Pull the latest uploads from the channel RSS feed on demand. */
    public function sync(): RedirectResponse
    {
        Gate::authorize('create', Video::class);

        $exit = Artisan::call('youtube:sync');
        $output = trim(Artisan::output());

        return back()->with($exit === 0 ? 'status' : 'error', $output !== '' ? $output : 'Sync finished.');
    }

    /** Hand-add a video the feed no longer returns — RSS only carries ~15. */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Video::class);

        $request->validate([
            'url' => ['required', 'string', 'max:500'],
            'title' => ['required', 'string', 'max:190'],
        ]);

        $id = Video::extractId($request->string('url')->toString());

        if ($id === null) {
            return back()->withInput()->withErrors([
                'url' => 'That does not look like a YouTube link or video id.',
            ]);
        }

        Video::updateOrCreate(
            ['youtube_id' => $id],
            [
                'title' => $request->string('title')->toString(),
                'published_at' => now(),
                // Marked manual so a later sync leaves the edited title alone.
                'is_manual' => true,
                'is_published' => true,
            ],
        );

        return back()->with('status', 'Video added.');
    }

    public function update(Request $request, Video $video): RedirectResponse
    {
        Gate::authorize('update', $video);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:190'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $video->update([
            'title' => $data['title'] ?? $video->title,
            'is_featured' => $request->boolean('is_featured'),
            'is_published' => $request->boolean('is_published'),
        ]);

        return back()->with('status', 'Video updated.');
    }

    public function destroy(Video $video): RedirectResponse
    {
        Gate::authorize('delete', $video);

        $video->delete();

        return back()->with('status', 'Video removed from the site.');
    }
}
