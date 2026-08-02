<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\StageSet;
use App\Models\User;

class StageSetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageStageSets->value);
    }

    public function view(User $user, StageSet $stageSet): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StageSet $stageSet): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, StageSet $stageSet): bool
    {
        return $user->isStaff() && $user->can(Permission::DeleteRecords->value);
    }
}
