<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManagePartners->value);
    }

    public function view(User $user, Partner $partner): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Partner $partner): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->isStaff() && $user->can(Permission::DeleteRecords->value);
    }

    /**
     * Seeing what a partner is owed is a separate, more sensitive permission
     * than managing their contact record. Deal terms are usually the first
     * thing you will want to keep to yourself.
     */
    public function viewCommissions(User $user, Partner $partner): bool
    {
        return $user->isStaff() && $user->can(Permission::ViewCommissions->value);
    }

    public function invite(User $user, Partner $partner): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageUsers->value);
    }
}
