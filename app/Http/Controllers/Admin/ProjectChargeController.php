<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ChargeKind;
use App\Enums\ChargeStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectChargeController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageCharges', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'kind' => ['required', 'in:extra,revision,maintenance'],
            'status' => ['required', 'in:quoted,approved'],
            'occurred_at' => ['required', 'date'],
            'commission_applies' => ['nullable', 'boolean'],
        ]);

        $project->charges()->create([
            ...$data,
            // Unchecked checkboxes are absent from the request, so the flag is
            // read explicitly rather than trusted to be present.
            'commission_applies' => $request->boolean('commission_applies'),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Charge added.');
    }

    public function approve(Request $request, Project $project, ProjectCharge $charge): RedirectResponse
    {
        Gate::authorize('manageCharges', $project);
        abort_unless($charge->project_id === $project->id, 404);

        $charge->approve();

        return back()->with('status', "\"{$charge->title}\" approved and added to the balance.");
    }

    public function update(Request $request, Project $project, ProjectCharge $charge): RedirectResponse
    {
        Gate::authorize('manageCharges', $project);
        abort_unless($charge->project_id === $project->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:quoted,approved,rejected,cancelled'],
        ]);

        $charge->update($data);

        return back()->with('status', 'Charge updated.');
    }

    public function destroy(Request $request, Project $project, ProjectCharge $charge): RedirectResponse
    {
        Gate::authorize('manageCharges', $project);
        abort_unless($charge->project_id === $project->id, 404);

        /*
         * Auto-generated retainer cycles are cancelled rather than deleted.
         *
         * A month that simply vanishes from the list looks like a billing gap
         * nobody can explain later; "cancelled" says what happened. Either way
         * the generator will not recreate it — its idempotency check includes
         * soft-deleted rows.
         */
        if ($charge->kind === ChargeKind::RetainerCycle) {
            $charge->update(['status' => ChargeStatus::Cancelled]);

            return back()->with('status', 'Retainer charge cancelled for that month.');
        }

        $charge->delete();

        return back()->with('status', 'Charge removed.');
    }
}
