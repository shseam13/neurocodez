<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Project;
use App\Models\ProjectStageLog;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StageService
{
    /**
     * Move a project to a stage and record it.
     *
     * The stage NAME is copied into the log, not just the id. Stage sets get
     * renamed and reordered while projects are live; without the snapshot a
     * rename silently rewrites history and a removal leaves blanks in the
     * timeline.
     */
    public function moveTo(Project $project, Stage $stage, ?User $by = null, ?string $note = null): ProjectStageLog
    {
        // Compared as integers, not strictly as-is. The models cast these now,
        // but a caller passing an unsaved model built from request input would
        // otherwise hit "1" !== 1 and be told the stage belongs to a different
        // set — which is both wrong and impossible to act on.
        if ((int) $stage->stage_set_id !== (int) $project->stage_set_id) {
            throw new RuntimeException('That stage belongs to a different stage set.');
        }

        return DB::transaction(function () use ($project, $stage, $by, $note) {
            $now = now();

            // Close the stage the project is leaving.
            $project->stageLogs()
                ->whereNull('exited_at')
                ->update(['exited_at' => $now]);

            $log = $project->stageLogs()->create([
                'stage_id' => $stage->getKey(),
                'stage_name_snapshot' => $stage->name,
                'entered_at' => $now,
                'changed_by' => $by?->getKey(),
                'note' => $note,
            ]);

            $project->forceFill(['current_stage_id' => $stage->getKey()]);

            if ($stage->is_terminal && $project->delivered_at === null) {
                $project->forceFill(['delivered_at' => $now]);
            }

            $project->save();

            return $log;
        });
    }

    /** Advance to the next stage in the set, if there is one. */
    public function advance(Project $project, ?User $by = null): ?ProjectStageLog
    {
        $next = $this->nextStage($project);

        return $next ? $this->moveTo($project, $next, $by) : null;
    }

    public function nextStage(Project $project): ?Stage
    {
        if (! $project->stage_set_id) {
            return null;
        }

        $position = $project->currentStage?->position ?? -1;

        return Stage::query()
            ->where('stage_set_id', $project->stage_set_id)
            ->where('position', '>', $position)
            ->orderBy('position')
            ->first();
    }

    /**
     * The stage timeline as a given audience should see it.
     *
     * Stages hidden from that audience COLLAPSE into the previous visible one
     * rather than disappearing. A client sees smooth, professional progress
     * ("In progress") instead of a gap where "Code review" and "QA" were —
     * and never learns the internal stage names.
     *
     * @return Collection<int, array{key: string, label: string, state: string}>
     */
    public function timelineFor(Project $project, AccountType $audience): Collection
    {
        if (! $project->stage_set_id) {
            return collect();
        }

        $stages = Stage::query()
            ->where('stage_set_id', $project->stage_set_id)
            ->orderBy('position')
            ->get();

        $currentPosition = $project->currentStage?->position;
        $timeline = collect();

        foreach ($stages as $stage) {
            if (! $stage->isVisibleTo($audience)) {
                continue;
            }

            $state = match (true) {
                $currentPosition === null => 'upcoming',
                $stage->position < $currentPosition => 'done',
                $stage->position === $currentPosition => 'current',
                default => 'upcoming',
            };

            $timeline->push([
                'key' => $stage->slug,
                'label' => $stage->labelFor($audience),
                'state' => $state,
            ]);
        }

        /*
         * If the live stage is hidden from this audience, the last visible
         * stage before it becomes their "current". Otherwise a project sitting
         * on an internal stage would show as further back than it really is.
         */
        if ($currentPosition !== null && ! $timeline->contains(fn ($s) => $s['state'] === 'current')) {
            $lastDone = $timeline->filter(fn ($s) => $s['state'] === 'done')->keys()->last();

            if ($lastDone !== null) {
                $timeline[$lastDone] = [...$timeline[$lastDone], 'state' => 'current'];
            }
        }

        return $timeline->values();
    }

    /**
     * Guard against removing a stage that history depends on.
     *
     * Stages are soft-deleted so `project_stage_logs` keeps resolving, but a
     * stage that is any project's live stage cannot be removed at all.
     */
    public function assertRemovable(Stage $stage): void
    {
        $live = Project::query()->where('current_stage_id', $stage->getKey())->count();

        if ($live > 0) {
            throw new RuntimeException(
                "\"{$stage->name}\" is the current stage of {$live} project(s). "
                .'Move them on first, or hide the stage instead of deleting it.'
            );
        }
    }
}
