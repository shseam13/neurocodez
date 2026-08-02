<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChargeStatus;
use App\Enums\InvoiceStatus;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly ProjectFinanceService $finance,
    ) {}

    /**
     * Draft an invoice for everything on a project that has not been billed yet.
     *
     * Line items are built from the original scope plus each approved charge,
     * itemised rather than lumped into one figure — a client querying a bill
     * should be able to see what each part was for.
     */
    public function draftFor(Project $project, ?User $by = null): Invoice
    {
        return DB::transaction(function () use ($project, $by) {
            $settings = CompanySetting::current();

            $invoice = $project->invoices()->create([
                'number' => $settings->nextInvoiceNumber(),
                'issued_at' => now(),
                'due_at' => now()->addDays((int) config('neuro.invoice.due_days', 14)),
                'status' => InvoiceStatus::Draft,
                // The column default only applies on insert, so a freshly
                // built Project still holds null in memory.
                'currency' => $project->currency ?: config('neuro.currency', 'BDT'),
                'created_by' => $by?->getKey(),
            ]);

            $position = 0;

            if ($project->agreed_amount->isPositive()) {
                $invoice->items()->create([
                    'description' => $project->title,
                    'qty' => 1,
                    'unit_price' => $project->agreed_amount,
                    'position' => $position++,
                ]);
            }

            foreach ($project->charges()->approved()->orderBy('occurred_at')->get() as $charge) {
                $invoice->items()->create([
                    'description' => $charge->title.' ('.$charge->kind->label().')',
                    'qty' => 1,
                    'unit_price' => $charge->amount,
                    'position' => $position++,
                ]);
            }

            return $this->recalculate($invoice->load('items'));
        });
    }

    /** Recompute stored totals from the line items. */
    public function recalculate(Invoice $invoice): Invoice
    {
        $subtotal = $invoice->items->reduce(
            fn (Money $carry, $item) => $carry->plus($item->line_total),
            Money::zero($invoice->currency),
        );

        /*
         * Money OBJECTS, never ->minor.
         *
         * These columns use MoneyCast, which treats a bare scalar as a MAJOR
         * amount (that is what a form posts). Handing it minor units made every
         * total 100x too large. Passing the object keeps the unit unambiguous —
         * which is the whole reason the type exists.
         */
        /*
         * `subtotal` stays the value of the whole engagement — the line items
         * describe everything the client is agreeing to, and hiding that on an
         * advance invoice would leave them unable to see what they signed up
         * for. `total` is only what is being asked for NOW, which on an advance
         * request is the percentage rather than the lot.
         */
        $invoice->forceFill(['subtotal' => $subtotal])->save();

        $invoice->forceFill([
            'total' => $invoice->billableSubtotal()
                ->plus($invoice->tax ?? Money::zero($invoice->currency)),
        ])->save();

        return $invoice->refresh();
    }

    /**
     * What the client still owes on this invoice.
     *
     * Payments are recorded against the PROJECT, not against an invoice, so this
     * is the project balance rather than an invoice-specific ledger. That keeps
     * one source of truth for money received — but it means two open invoices on
     * one project will each show the same project-level balance, which is why
     * the template labels it as such.
     */
    public function projectBalance(Invoice $invoice): Money
    {
        return $this->finance->amountDue($invoice->project);
    }

    /**
     * What this document should print as still owing.
     *
     * An advance request asks for a specific figure, so the project-wide
     * balance would contradict the "Due now" line directly above it — the
     * client would read "Due now 15,000" and then "Balance due 25,000" on the
     * same page and have no idea which to pay.
     *
     * Ordinary invoices keep the project balance: payments are recorded against
     * the project, and for a single invoice the two are the same figure anyway.
     */
    public function balanceFor(Invoice $invoice): Money
    {
        if (! $invoice->isAdvanceRequest()) {
            return $this->projectBalance($invoice);
        }

        return ($invoice->total ?? Money::zero($invoice->currency))
            ->minus($this->finance->totalPaid($invoice->project));
    }

    public function markSent(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->forceFill(['status' => InvoiceStatus::Sent])->save();
        }

        return $invoice;
    }

    /** True when the project has nothing left to bill. */
    public function hasUnbilledWork(Project $project): bool
    {
        $billable = $project->agreed_amount->plus($this->finance->approvedExtras($project));

        $invoiced = Money::ofMinor(
            (int) $project->invoices()->where('status', '!=', InvoiceStatus::Void)->sum('total'),
            $project->currency,
        );

        return $billable->minus($invoiced)->isPositive();
    }

    /** Anything approved after the last invoice was raised. */
    public function unbilledCharges(Project $project): int
    {
        return $project->charges()
            ->where('status', ChargeStatus::Approved)
            ->count();
    }
}
