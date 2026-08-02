<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\CommissionBasis;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use App\Models\Partner;
use App\Models\StageSet;
use App\Services\CommissionService;
use App\Services\ProjectFinanceService;
use App\Services\StageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectFinanceService $finance): View
    {
        Gate::authorize('viewAny', Project::class);

        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();

        $projects = Project::query()
            ->with(['client', 'partner', 'currentStage'])
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('title', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            }))
            ->when($status === 'overdue', fn ($q) => $q->overdue())
            ->when($status !== '' && $status !== 'overdue', fn ($q) => $q->where('status', $status))
            ->when($status === '', fn ($q) => $q->open())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.projects.index', [
            'projects' => $projects,
            'finance' => $projects->mapWithKeys(
                fn (Project $p) => [$p->id => $finance->summarise($p)]
            ),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Project::class);

        $project = new Project([
            'billed_to' => 'client',
            'commission_basis' => config('neuro.default_commission_basis', 'collected'),
            'currency' => config('neuro.currency', 'BDT'),
            'status' => ProjectStatus::Active,
        ]);

        // Pre-fill when starting from a client page or spinning off a follow-up.
        $project->client_id = $request->integer('client') ?: null;
        $project->parent_id = $request->integer('parent') ?: null;

        if ($project->parent_id && $parent = Project::find($project->parent_id)) {
            $project->client_id = $parent->client_id;
            $project->partner_id = $parent->partner_id;
        }

        return view('admin.projects.form', $this->formData($project));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $project = Project::create($this->validated($request));

        // Put the project on the first stage of its set so the timeline is not
        // blank the moment it is created.
        if ($project->stage_set_id && $first = $project->stageSet?->firstStage()) {
            app(StageService::class)->moveTo($project, $first, $request->user());
        }

        return redirect()
            ->route('admin.projects.show', $project)
            ->with('status', "{$project->title} created.");
    }

    public function show(
        Project $project,
        ProjectFinanceService $finance,
        CommissionService $commission,
        StageService $stages,
    ): View {
        Gate::authorize('view', $project);

        $project->load([
            'client', 'partner', 'parent', 'followUps', 'stageSet.stages',
            'currentStage', 'charges.createdBy', 'payments', 'stageLogs.changedBy',
            'invoices', 'commissionPayouts', 'files.uploadedBy',
        ]);

        return view('admin.projects.show', [
            'project' => $project,
            'finance' => $finance->summarise($project),
            'commission' => Gate::allows('viewCommission', $project)
                ? $commission->summarise($project)
                : null,
            'timeline' => $stages->timelineFor($project, AccountType::Staff),
            'clientTimeline' => $stages->timelineFor($project, AccountType::Client),
            'nextStage' => $stages->nextStage($project),
        ]);
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('admin.projects.form', $this->formData($project));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($this->validated($request, $project));

        return redirect()
            ->route('admin.projects.show', $project)
            ->with('status', 'Changes saved.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        // Payment history is the record of what actually changed hands. Losing
        // it to a mis-click is not recoverable from anywhere else.
        if ($project->payments()->exists() || $project->invoices()->exists()) {
            return back()->withErrors([
                'project' => 'This project has payments or invoices. Cancel it instead of deleting — the financial record must stay.',
            ]);
        }

        $title = $project->title;
        $project->delete();

        return redirect()->route('admin.projects.index')->with('status', "{$title} deleted.");
    }

    private function formData(Project $project): array
    {
        return [
            'project' => $project,
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'partners' => Partner::where('is_active', true)->orderBy('name')->get(),
            'stageSets' => StageSet::where('is_active', true)->with('stages')->orderBy('name')->get(),
            'parents' => $project->client_id
                ? Project::where('client_id', $project->client_id)
                    ->whereKeyNot($project->getKey())
                    ->orderBy('title')->get()
                : collect(),
        ];
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'parent_id' => ['nullable', 'exists:projects,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'agreed_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_basis' => ['required', 'in:collected,agreed'],
            // Optional: omitted means the client pays, which is the common case.
            'billed_to' => ['nullable', 'in:client,partner'],
            'stage_set_id' => ['nullable', 'exists:stage_sets,id'],
            'status' => ['required', 'in:active,on_hold,delivered,cancelled'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'is_retainer' => ['nullable', 'boolean'],
            'retainer_amount' => ['nullable', 'required_if:is_retainer,1', 'numeric', 'min:0'],
            // 1-28 only: every month has those days, so a retainer can never
            // silently skip February.
            'retainer_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'retainer_starts_on' => ['nullable', 'date'],
            'retainer_ends_on' => ['nullable', 'date', 'after_or_equal:retainer_starts_on'],
        ]);

        $data['billed_to'] = $data['billed_to'] ?? 'client';
        $data['is_retainer'] = $request->boolean('is_retainer');

        if (! $data['is_retainer']) {
            $data['retainer_amount'] = null;
        }

        /*
         * A project must never be its own parent, and an existing project's
         * commission rate is only overwritten when a value is actually
         * submitted — leaving the field blank keeps the agreed snapshot.
         */
        if ($project && ($data['parent_id'] ?? null) == $project->getKey()) {
            $data['parent_id'] = null;
        }

        if ($project && blank($request->input('commission_percent'))) {
            unset($data['commission_percent']);
        }

        return $data;
    }
}
