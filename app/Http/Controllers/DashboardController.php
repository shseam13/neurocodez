<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Models\Lead;
use App\Models\Project;
use App\Services\CommissionService;
use App\Services\ProjectFinanceService;
use App\Services\StageService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProjectFinanceService $finance,
        private readonly CommissionService $commission,
        private readonly StageService $stages,
    ) {}

    /** Owner/staff view: the money picture. */
    public function staff(): View
    {
        $totals = $this->finance->companyTotals();

        return view('admin.dashboard', [
            'totals' => $totals,
            'commissionsPayable' => $this->commission->totalPayable(),
            'overdue' => Project::query()->overdue()->with('client')->limit(5)->get(),
            'recent' => Project::query()->open()->with('client', 'currentStage')
                ->latest()->limit(5)->get(),
            'newLeads' => Lead::query()->new()->count(),
        ]);
    }

    /**
     * Client portal.
     *
     * Scoped to the signed-in user's client_id — not to a request parameter,
     * which could be tampered with. There is deliberately no commission data
     * anywhere in this payload.
     */
    public function client(): View
    {
        $projects = Project::query()
            ->where('client_id', auth()->user()->client_id)
            // Only files explicitly shared. Eager-loading the filtered relation
            // means an internal file cannot reach the view even by accident.
            ->with(['currentStage', 'files' => fn ($q) => $q->clientVisible()->latest()])
            ->latest()
            ->get();

        return view('portal.client.dashboard', [
            'projects' => $projects->map(fn (Project $p) => [
                'project' => $p,
                'finance' => $this->finance->summarise($p),
                'timeline' => $this->stages->timelineFor($p, AccountType::Client),
            ]),
        ]);
    }

    /**
     * Partner portal.
     *
     * Scoped to projects this partner actually brought. They get progress and
     * shared files as well as commission, because a partner is often the point
     * of contact — but the stage timeline is still filtered to the Partner
     * audience, so internal stages stay internal.
     */
    public function partner(): View
    {
        $partner = auth()->user()->partner;

        if (! $partner) {
            return view('portal.partner.dashboard', [
                'summary' => ['total_owed' => null, 'total_paid' => null, 'total_due' => null, 'projects' => []],
                'projects' => collect(),
            ]);
        }

        $projects = Project::query()
            ->where('partner_id', $partner->id)
            ->with([
                'client',
                'currentStage',
                'files' => fn ($q) => $q->clientVisible()->latest(),
            ])
            ->latest()
            ->get();

        return view('portal.partner.dashboard', [
            'summary' => $this->commission->summarisePartner($partner),
            'projects' => $projects->map(fn (Project $p) => [
                'project' => $p,
                'finance' => $this->finance->summarise($p),
                'timeline' => $this->stages->timelineFor($p, AccountType::Partner),
            ]),
        ]);
    }
}
