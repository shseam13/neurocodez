<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageProjects->value);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isStaff() && $user->can(Permission::DeleteRecords->value);
    }

    /** Adding or approving extra work is money movement, not editing. */
    public function manageCharges(User $user, Project $project): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageProjects->value);
    }

    /** Seeing what the partner earns on this project is separately gated. */
    public function viewCommission(User $user, Project $project): bool
    {
        return $user->isStaff() && $user->can(Permission::ViewCommissions->value);
    }
}
