<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Shared authorisation for public-site content.
 *
 * Posts, portfolio items and videos are the same decision — "may this person
 * publish to the website" — so they share one policy rather than three
 * identical ones. Registered explicitly in AppServiceProvider because the
 * class name does not follow Laravel's Model/ModelPolicy convention.
 */
class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageContent->value);
    }

    public function view(User $user, $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, $model = null): bool
    {
        return $user->isStaff() && $user->can(Permission::DeleteRecords->value);
    }
}
