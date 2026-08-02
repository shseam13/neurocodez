<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Stage;
use App\Services\StageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ProjectStageController extends Controller
{
    public function __construct(private readonly StageService $stages) {}

    public function move(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validate([
            'stage_id' => ['required', 'exists:stages,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->stages->moveTo(
                $project,
                Stage::findOrFail($data['stage_id']),
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            // e.g. a stage from a different set — surfaced, not swallowed.
            return back()->withErrors(['stage_id' => $e->getMessage()]);
        }

        return back()->with('status', 'Stage updated.');
    }

    public function advance(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $log = $this->stages->advance($project, $request->user());

        return back()->with('status', $log
            ? "Moved to {$log->stage_name_snapshot}."
            : 'Already at the final stage.');
    }
}
