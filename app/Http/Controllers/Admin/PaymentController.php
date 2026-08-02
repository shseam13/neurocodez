<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Project;
use App\Services\ProjectFinanceService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function store(Request $request, Project $project, ProjectFinanceService $finance): RedirectResponse
    {
        Gate::authorize('create', Payment::class);

        $data = $request->validate([
            // Negative values are allowed on purpose: a refund or a correction
            // is recorded as its own row rather than by editing history.
            'amount' => ['required', 'numeric', 'not_in:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:bkash,nagad,rocket,bank,cash,other'],
            'reference' => ['nullable', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $project->payments()->create([
            ...$data,
            'recorded_by' => $request->user()->id,
        ]);

        $due = $finance->amountDue($project->fresh());

        // Overpayment is allowed — advances and rounding happen — but it should
        // never pass silently.
        $message = $due->isNegative()
            ? 'Payment recorded — note this project is now overpaid by '.$due->abs()->formatWithCurrency().'.'
            : 'Payment recorded.';

        return back()->with('status', $message);
    }

    public function destroy(Request $request, Project $project, Payment $payment): RedirectResponse
    {
        Gate::authorize('delete', $payment);
        abort_unless($payment->project_id === $project->id, 404);

        $payment->delete();

        return back()->with('status', 'Payment removed. It stays in the audit log.');
    }
}
