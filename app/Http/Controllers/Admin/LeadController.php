<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Lead::class);

        $status = $request->string('status')->toString();

        return view('admin.leads.index', [
            'leads' => Lead::query()
                ->with('convertedClient')
                // Default view is work still to do; spam and converted are
                // reachable but out of the way.
                ->when($status === '', fn ($q) => $q->actionable())
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'counts' => [
                'new' => Lead::query()->new()->count(),
                'actionable' => Lead::query()->actionable()->count(),
            ],
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,converted,spam'],
        ]);

        $lead->update([
            ...$data,
            'handled_at' => now(),
            'handled_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Enquiry updated.');
    }

    /** Turn an enquiry into a real client record. */
    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('convert', $lead);

        $alreadyConverted = $lead->converted_client_id !== null;
        $client = $lead->convertToClient($request->user());

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', $alreadyConverted
                ? 'That enquiry was already converted — here is the client.'
                : "{$client->name} added as a client. The enquiry is attached to their notes.");
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        return back()->with('status', 'Enquiry deleted.');
    }
}
