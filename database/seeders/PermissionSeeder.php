<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as Perm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds roles and permissions.
 *
 * Safe to re-run: it syncs rather than duplicates, so it is the single place to
 * change when you decide to start restricting staff.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Perm::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Super admin holds no explicit permissions by design — Gate::before in
        // AppServiceProvider grants everything. Listing them would create two
        // sources of truth that drift apart.
        Role::findOrCreate(User::ROLE_SUPER_ADMIN, 'web');

        /*
         * v1: admin gets everything. To restrict later, remove entries from
         * this array and re-run the seeder — no application code changes.
         *
         * e.g. to hide partner deal terms from staff, drop:
         *   Perm::ViewCommissions, Perm::ManageCommissions
         */
        $adminPermissions = array_values(array_diff(
            Perm::names(),
            array_map(fn (Perm $p) => $p->value, Perm::ownerOnly()),
        ));

        Role::findOrCreate(User::ROLE_ADMIN, 'web')
            ->syncPermissions($adminPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
