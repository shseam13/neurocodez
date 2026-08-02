<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageLeads->value);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->viewAny($user);
    }

    /** Turning an enquiry into a client creates a client record. */
    public function convert(User $user, Lead $lead): bool
    {
        return $user->isStaff()
            && $user->can(Permission::ManageLeads->value)
            && $user->can(Permission::ManageClients->value);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isStaff() && $user->can(Permission::DeleteRecords->value);
    }
}
