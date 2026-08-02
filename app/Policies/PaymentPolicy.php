<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManagePayments->value);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Deleting a payment is owner-only.
     *
     * A payment row is the record that money changed hands. Losing one to a
     * mis-click cannot be reconstructed from anywhere else in the system.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->isStaff() && $user->can(Permission::DeleteRecords->value);
    }
}
