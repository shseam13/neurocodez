<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;

/**
 * Super admin passes everything via Gate::before, so nothing here needs to
 * special-case the owner.
 *
 * v1 grants `admin` all of these permissions, so staff currently have full
 * access. Revoking one in PermissionSeeder is all it takes to restrict — no
 * change here, which is the whole point of routing checks through policies.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageClients->value);
    }

    public function view(User $user, Client $client): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Client $client): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Client $client): bool
    {
        // Deleting is separated from editing on purpose: it is the permission
        // most likely to be pulled from staff first.
        return $user->isStaff() && $user->can(Permission::DeleteRecords->value);
    }

    /** Only the owner may hand out portal access. */
    public function invite(User $user, Client $client): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageUsers->value);
    }
}
