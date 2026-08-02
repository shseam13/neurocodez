<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StageSet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StageSetController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', StageSet::class);

        return view('admin.stage-sets.index', [
            'sets' => StageSet::query()
                ->withCount(['stages', 'projects'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', StageSet::class);

        return view('admin.stage-sets.form', ['set' => new StageSet]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', StageSet::class);

        $set = StageSet::create($this->validated($request));

        return redirect()
            ->route('admin.stage-sets.edit', $set)
            ->with('status', 'Stage set created. Add its stages below.');
    }

    public function edit(StageSet $stageSet): View
    {
        Gate::authorize('update', $stageSet);

        return view('admin.stage-sets.form', [
            'set' => $stageSet->load('stages'),
        ]);
    }

    public function update(Request $request, StageSet $stageSet): RedirectResponse
    {
        Gate::authorize('update', $stageSet);

        $stageSet->update($this->validated($request));

        return back()->with('status', 'Stage set saved.');
    }

    /** Copy a set so it can be adapted without touching live projects. */
    public function duplicate(StageSet $stageSet): RedirectResponse
    {
        Gate::authorize('create', StageSet::class);

        $copy = $stageSet->duplicate();

        return redirect()
            ->route('admin.stage-sets.edit', $copy)
            ->with('status', "Copied to \"{$copy->name}\".");
    }

    public function makeDefault(StageSet $stageSet): RedirectResponse
    {
        Gate::authorize('update', $stageSet);

        $stageSet->makeDefault();

        return back()->with('status', "\"{$stageSet->name}\" is now the default for new projects.");
    }

    public function destroy(StageSet $stageSet): RedirectResponse
    {
        Gate::authorize('delete', $stageSet);

        /*
         * A set in use cannot be deleted.
         *
         * Projects hold `stage_set_id`, and their stage history references
         * individual stages. Removing the set would leave live projects with a
         * pipeline that no longer exists.
         */
        if ($stageSet->projects()->exists()) {
            return back()->withErrors([
                'set' => "\"{$stageSet->name}\" is used by ".$stageSet->projects()->count().
                    ' project(s). Deactivate it instead — existing projects keep working and it stops appearing for new ones.',
            ]);
        }

        $name = $stageSet->name;
        $stageSet->delete();

        return redirect()->route('admin.stage-sets.index')->with('status', "\"{$name}\" deleted.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
