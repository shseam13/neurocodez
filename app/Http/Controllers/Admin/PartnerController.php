<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\CommissionService;
use App\Services\InvitationService;
use RuntimeException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PartnerController extends Controller
{
    /*
     * Explicit per-action gates rather than authorizeResource(): Laravel 11+
     * removed $this->middleware() from controllers, which that helper needs.
     */

    public function index(Request $request, CommissionService $commission): View
    {
        Gate::authorize('viewAny', Partner::class);

        $search = $request->string('q')->trim()->toString();

        $partners = Partner::query()
            ->withCount('projects')
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Commission figures are a separate permission from the contact record.
        $canSeeCommissions = Gate::allows('viewCommissions', new Partner);

        return view('admin.partners.index', [
            'partners' => $partners,
            'search' => $search,
            'canSeeCommissions' => $canSeeCommissions,
            'due' => $canSeeCommissions
                ? $partners->mapWithKeys(fn (Partner $r) => [
                    $r->id => $commission->summarisePartner($r)['total_due'],
                ])
                : collect(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Partner::class);

        return view('admin.partners.form', ['partner' => new Partner]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Partner::class);

        $partner = Partner::create($this->validated($request));

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('status', "{$partner->name} added.");
    }

    public function show(Partner $partner, CommissionService $commission): View
    {
        Gate::authorize('view', $partner);
        Gate::authorize('viewCommissions', $partner);

        $partner->load('portalUsers');

        return view('admin.partners.show', [
            'partner' => $partner,
            'summary' => $commission->summarisePartner($partner),
            'clients' => $partner->clients()->withCount('projects')->orderBy('name')->get(),
        ]);
    }

    public function edit(Partner $partner): View
    {
        Gate::authorize('update', $partner);

        return view('admin.partners.form', ['partner' => $partner]);
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        Gate::authorize('update', $partner);

        $partner->update($this->validated($request));

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('status', 'Changes saved. Existing projects keep the rate they were agreed at.');
    }

    public function destroy(Partner $partner): RedirectResponse
    {
        Gate::authorize('delete', $partner);

        if ($partner->projects()->exists()) {
            return back()->withErrors([
                'partner' => "{$partner->name} is attached to projects. Deleting would lose the commission record.",
            ]);
        }

        $name = $partner->name;
        $partner->delete();

        return redirect()->route('admin.partners.index')->with('status', "{$name} deleted.");
    }

    /** Give this partner a portal login. */
    public function invite(Request $request, Partner $partner, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('manageUsers');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        try {
            $invitations->invitePartner($partner, $data['name'], $data['email'], $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['invite' => $e->getMessage()]);
        }

        return back()->with('status', "Invitation sent to {$data['email']}.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            // Capped at 100: a rate above that would mean paying out more than
            // the project earns.
            'default_commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
