<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Partner;
use App\Services\InvitationService;
use App\Services\ProjectFinanceService;
use RuntimeException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    /*
     * Authorisation is called explicitly per action rather than via
     * authorizeResource(): Laravel 11+ removed $this->middleware() from
     * controllers, which authorizeResource() depends on. Explicit calls are
     * also easier to read — you can see the gate right next to the action.
     */

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Client::class);

        $search = $request->string('q')->trim()->toString();

        return view('admin.clients.index', [
            'clients' => Client::query()
                ->with('partner')
                ->withCount('projects')
                ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                }))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('admin.clients.form', [
            'client' => new Client,
            'partners' => Partner::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        $client = Client::create($this->validated($request));

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', "{$client->name} added.");
    }

    public function show(Client $client, ProjectFinanceService $finance): View
    {
        Gate::authorize('view', $client);

        $client->load(['partner', 'portalUsers', 'projects' => fn ($q) => $q->latest()]);

        return view('admin.clients.show', [
            'client' => $client,
            'finance' => $client->projects->mapWithKeys(
                fn ($project) => [$project->id => $finance->summarise($project)]
            ),
        ]);
    }

    public function edit(Client $client): View
    {
        Gate::authorize('update', $client);

        return view('admin.clients.form', [
            'client' => $client,
            'partners' => Partner::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->update($this->validated($request, $client));

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', 'Changes saved.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('delete', $client);

        /*
         * Refuse to delete a client that has projects.
         *
         * Cascading would take their payment history and invoices with it —
         * records you may need years later for a dispute or for tax.
         */
        if ($client->projects()->exists()) {
            return back()->withErrors([
                'client' => "{$client->name} has projects. Archive them first, or keep the client for the record.",
            ]);
        }

        $name = $client->name;
        $client->delete();

        return redirect()->route('admin.clients.index')->with('status', "{$name} deleted.");
    }

    /** Give this client a portal login. */
    public function invite(Request $request, Client $client, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('manageUsers');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        try {
            $invitations->inviteClient($client, $data['name'], $data['email'], $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['invite' => $e->getMessage()]);
        }

        return back()->with('status', "Invitation sent to {$data['email']}.");
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
