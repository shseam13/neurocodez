<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProjectFile;
use App\Models\User;

class ProjectFilePolicy
{
    public function create(User $user): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageProjects->value);
    }

    /**
     * Who may download a file.
     *
     * This is the check that matters. Files are served through a controller
     * rather than a public or signed URL precisely so this runs on every
     * request — a signed link, once issued, is shareable by anyone who has it.
     */
    public function download(User $user, ProjectFile $file): bool
    {
        if ($user->isStaff()) {
            return $user->can(Permission::ManageProjects->value);
        }

        if ($user->isClient()) {
            // Two conditions, both required: it must be THEIR project, and the
            // file must be one you chose to share. Internal working files stay
            // invisible even to the client who owns the project.
            return $file->client_visible
                && $user->client_id !== null
                && $file->project->client_id === $user->client_id;
        }

        if ($user->isPartner()) {
            /*
             * Partners can act as the point of contact — sometimes the end
             * client never deals with us at all — so they get the same shared
             * deliverables the client would, but only for projects they
             * actually brought, and never internal working files.
             *
             * Worth being deliberate about: this means a partner can pass your
             * work on. It is the arrangement they chose, not an oversight.
             */
            return $file->client_visible
                && $user->partner_id !== null
                && $file->project->partner_id === $user->partner_id;
        }

        return false;
    }

    public function update(User $user, ProjectFile $file): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, ProjectFile $file): bool
    {
        return $user->isStaff() && $user->can(Permission::ManageProjects->value);
    }
}
