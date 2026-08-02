<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Enums\Permission;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageInvoices->value);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isStaff()) {
            return $user->can(Permission::ManageInvoices->value);
        }

        /*
         * A client may see their own invoices, but only once sent.
         *
         * A draft is a working document — the figures may still be wrong, and a
         * client seeing one would reasonably treat it as a bill.
         */
        if ($user->isClient()) {
            return $user->client_id !== null
                && $invoice->project->client_id === $user->client_id
                && $invoice->status->isLocked();
        }

        // A partner billed directly is the one who owes the money, so they see
        // the invoice; otherwise it is none of their business.
        if ($user->isPartner()) {
            return $user->partner_id !== null
                && $invoice->project->partner_id === $user->partner_id
                && $invoice->project->isBilledToPartner()
                && $invoice->status->isLocked();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** A sent invoice is locked — editing one a client already holds is how disputes start. */
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user) && ! $invoice->status->isLocked();
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isStaff() && $user->can(Permission::DeleteRecords->value);
    }
}
