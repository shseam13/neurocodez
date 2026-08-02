<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CommissionPayout;
use App\Models\User;

class CommissionPayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ViewCommissions->value);
    }

    /** Recording a payout is a separate, higher permission than seeing one. */
    public function create(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageCommissions->value);
    }

    public function delete(User $user, CommissionPayout $payout): bool
    {
        return $user->isStaff() && $user->can(Permission::DeleteRecords->value);
    }
}
