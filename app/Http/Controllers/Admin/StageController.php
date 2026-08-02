<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stage;
use App\Models\StageSet;
use App\Services\StageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class StageController extends Controller
{
    public function __construct(private readonly StageService $stages) {}

    public function store(Request $request, StageSet $stageSet): RedirectResponse
    {
        Gate::authorize('update', $stageSet);

        $data = $this->validated($request);

        /*
         * Append after the current last stage. Note max() returns null on an
         * empty set — (int) null + 1 would start the first stage at 1 while the
         * seeder starts at 0, so the null case is handled explicitly.
         * max()+1 rather than count() so a soft-deleted stage cannot cause a
         * position collision.
         */
        $lastPosition = $stageSet->stages()->max('position');

        $stageSet->stages()->create([
            ...$data,
            'slug' => $stageSet->suggestSlug($data['name']),
            'position' => $lastPosition === null ? 0 : (int) $lastPosition + 1,
        ]);

        return back()->with('status', 'Stage added.');
    }

    public function update(Request $request, StageSet $stageSet, Stage $stage): RedirectResponse
    {
        Gate::authorize('update', $stageSet);
        abort_unless($stage->stage_set_id === $stageSet->id, 404);

        /*
         * Renaming is safe and deliberate: `project_stage_logs` stores the name
         * as it was at the time, so history keeps reading correctly while new
         * transitions use the new name.
         */
        $stage->update($this->validated($request));

        return back()->with('status', 'Stage saved. Existing project history keeps the old name.');
    }

    public function move(Request $request, StageSet $stageSet, Stage $stage): RedirectResponse
    {
        Gate::authorize('update', $stageSet);
        abort_unless($stage->stage_set_id === $stageSet->id, 404);

        $direction = $request->string('direction')->toString();
        $ordered = $stageSet->stages()->get();
        $index = $ordered->search(fn (Stage $s) => $s->id === $stage->id);

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= $ordered->count()) {
            return back();
        }

        // Swap positions with the neighbour, then renumber the whole set so
        // positions stay contiguous no matter how they were edited before.
        $neighbour = $ordered[$target];
        $ordered[$index] = $neighbour;
        $ordered[$target] = $stage;

        foreach ($ordered->values() as $i => $item) {
            $item->forceFill(['position' => $i])->save();
        }

        return back();
    }

    public function destroy(StageSet $stageSet, Stage $stage): RedirectResponse
    {
        Gate::authorize('update', $stageSet);
        abort_unless($stage->stage_set_id === $stageSet->id, 404);

        try {
            $this->stages->assertRemovable($stage);
        } catch (RuntimeException $e) {
            return back()->withErrors(['stage' => $e->getMessage()]);
        }

        // Soft delete only — project_stage_logs still points here, and history
        // must keep resolving.
        $stage->delete();

        return back()->with('status', 'Stage removed. Past project history is unaffected.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'client_label' => ['nullable', 'string', 'max:120'],
            'is_terminal' => ['nullable', 'boolean'],
            'visible_to_client' => ['nullable', 'boolean'],
            'visible_to_partner' => ['nullable', 'boolean'],
        ]) + [
            'is_terminal' => $request->boolean('is_terminal'),
            'visible_to_client' => $request->boolean('visible_to_client'),
            'visible_to_partner' => $request->boolean('visible_to_partner'),
        ];
    }
}
