<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use App\Models\Project;
use App\Models\Partner;
use App\Services\CommissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommissionPayoutController extends Controller
{
    /** Everything owed to every partner, in one place. */
    public function index(CommissionService $commission): View
    {
        Gate::authorize('viewAny', CommissionPayout::class);

        $rows = Partner::query()
            ->with(['projects.client', 'projects.commissionPayouts'])
            ->orderBy('name')
            ->get()
            ->map(fn (Partner $r) => [
                'partner' => $r,
                'summary' => $commission->summarisePartner($r),
            ])
            // Partners with nothing outstanding are noise on a "who do I owe"
            // screen, but keep any with history so figures can be checked.
            ->filter(fn (array $row) => $row['summary']['total_owed']->isPositive())
            ->sortByDesc(fn (array $row) => $row['summary']['total_due']->minor)
            ->values();

        return view('admin.payouts.index', [
            'rows' => $rows,
            'totalDue' => $commission->totalPayable(),
        ]);
    }

    public function store(Request $request, Project $project, CommissionService $commission): RedirectResponse
    {
        Gate::authorize('create', CommissionPayout::class);

        abort_unless($project->partner_id !== null, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:bkash,nagad,rocket,bank,cash,other'],
            'reference' => ['nullable', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $project->commissionPayouts()->create([
            ...$data,
            'partner_id' => $project->partner_id,
            'recorded_by' => $request->user()->id,
        ]);

        $remaining = $commission->due($project->fresh());

        return back()->with('status', $remaining->isZero()
            ? 'Payout recorded. This project is settled with the partner.'
            : "Payout recorded. {$remaining->formatWithCurrency()} still owed on this project.");
    }

    public function destroy(Request $request, Project $project, CommissionPayout $payout): RedirectResponse
    {
        Gate::authorize('delete', $payout);
        abort_unless($payout->project_id === $project->id, 404);

        $payout->delete();

        return back()->with('status', 'Payout removed. It stays in the audit log.');
    }
}
