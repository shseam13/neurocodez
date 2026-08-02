<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Super admin passes every gate.
         *
         * Returning null (not false) when the check does not apply is
         * important — false here would short-circuit and deny everyone else
         * before their own policy ever runs.
         */
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });

        /*
         * Portal accounts never hold roles or permissions. Even if one were
         * somehow assigned, this refuses staff-side abilities structurally
         * rather than relying on the UI to hide the link.
         */
        Gate::after(function (User $user, string $ability, ?bool $result): ?bool {
            if ($result === true && ! $user->isStaff() && str_starts_with($ability, 'manage_')) {
                return false;
            }

            return null;
        });

        /*
         * Posts, portfolio items and videos share one policy — the decision is
         * identical for all three ("may this person publish to the website"),
         * so they are mapped explicitly rather than duplicated into three
         * classes Laravel would auto-discover.
         */
        /*
         * Account management has no model of its own, so it is a named gate
         * rather than a policy. Both conditions matter: staff-only by type, and
         * gated on the permission so it can be revoked later without a refactor.
         */
        Gate::define('manageUsers', fn (User $user): bool => $user->isStaff()
            && $user->can(\App\Enums\Permission::ManageUsers->value));

        Gate::policy(\App\Models\Post::class, \App\Policies\ContentPolicy::class);
        Gate::policy(\App\Models\PortfolioItem::class, \App\Policies\ContentPolicy::class);
        Gate::policy(\App\Models\Video::class, \App\Policies\ContentPolicy::class);
        Gate::policy(\App\Models\Tag::class, \App\Policies\ContentPolicy::class);

        // Catch relations that were not eager-loaded, and mass-assignment of
        // attributes that do not exist — both while developing only.
        Model::preventLazyLoading($this->app->isLocal());
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());
    }
}
