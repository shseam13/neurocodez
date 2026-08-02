<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Permission;
use App\Models\Client;
use App\Models\Project;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function staff(string $role = User::ROLE_ADMIN): User
    {
        $user = User::create([
            'name' => 'Staff', 'email' => 'staff@example.com',
            'password' => Hash::make('secret'), 'type' => AccountType::Staff, 'is_active' => true,
        ]);

        return $user->assignRole($role);
    }

    #[Test]
    public function staff_can_create_a_client(): void
    {
        $this->actingAs($this->staff())
            ->post('/admin/clients', ['name' => 'Rahim Traders', 'email' => 'rahim@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', ['name' => 'Rahim Traders']);
    }

    #[Test]
    public function staff_can_create_a_partner_with_a_rate(): void
    {
        $this->actingAs($this->staff())
            ->post('/admin/partners', ['name' => 'Karim', 'default_commission_percent' => 12.5])
            ->assertRedirect();

        $this->assertDatabaseHas('partners', ['name' => 'Karim', 'default_commission_percent' => 12.50]);
    }

    #[Test]
    public function a_commission_rate_above_100_percent_is_rejected(): void
    {
        // Paying out more than the project earns is never intended.
        $this->actingAs($this->staff())
            ->post('/admin/partners', ['name' => 'Greedy', 'default_commission_percent' => 150])
            ->assertSessionHasErrors('default_commission_percent');

        $this->assertDatabaseMissing('partners', ['name' => 'Greedy']);
    }

    #[Test]
    public function editing_a_partners_default_rate_leaves_existing_projects_untouched(): void
    {
        $staff = $this->staff();
        $partner = Partner::create(['name' => 'Karim', 'default_commission_percent' => 10]);
        $client = Client::create(['name' => 'Rahim Traders', 'partner_id' => $partner->id]);

        $project = Project::create([
            'client_id' => $client->id,
            'partner_id' => $partner->id,
            'title' => 'Portal v2',
            'agreed_amount' => 50000,
        ]);

        $this->assertSame('10.00', $project->commission_percent);

        $this->actingAs($staff)->put("/admin/partners/{$partner->id}", [
            'name' => 'Karim',
            'default_commission_percent' => 25,
        ])->assertRedirect();

        $this->assertSame('25.00', $partner->fresh()->default_commission_percent);
        // The whole point: the agreed project keeps its own rate.
        $this->assertSame('10.00', $project->fresh()->commission_percent);
    }

    #[Test]
    public function a_plain_admin_cannot_delete_records(): void
    {
        // DeleteRecords is owner-only by design — an accidental wipe of a
        // client takes their payment history with it.
        $client = Client::create(['name' => 'Nobody Ltd']);

        $this->actingAs($this->staff(User::ROLE_ADMIN))
            ->delete("/admin/clients/{$client->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'deleted_at' => null]);
    }

    #[Test]
    public function a_client_with_projects_cannot_be_deleted_even_by_the_owner(): void
    {
        $client = Client::create(['name' => 'Rahim Traders']);
        Project::create(['client_id' => $client->id, 'title' => 'Site', 'agreed_amount' => 1000]);

        $this->actingAs($this->staff(User::ROLE_SUPER_ADMIN))
            ->delete("/admin/clients/{$client->id}")
            ->assertSessionHasErrors('client');

        // Cascading would take payment history and invoices with it.
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'deleted_at' => null]);
    }

    #[Test]
    public function the_owner_can_delete_a_client_with_no_projects(): void
    {
        $client = Client::create(['name' => 'Nobody Ltd']);

        $this->actingAs($this->staff(User::ROLE_SUPER_ADMIN))
            ->delete("/admin/clients/{$client->id}")
            ->assertRedirect('/admin/clients');

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    #[Test]
    public function portal_accounts_cannot_reach_admin_crud(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $portalUser = User::create([
            'name' => 'Client', 'email' => 'c@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Client, 'client_id' => $client->id, 'is_active' => true,
        ]);

        foreach (['/admin/clients', '/admin/partners', '/admin/clients/create'] as $url) {
            $this->actingAs($portalUser)->get($url)->assertRedirect('/portal');
        }
    }

    #[Test]
    public function search_narrows_the_client_list(): void
    {
        Client::create(['name' => 'Rahim Traders']);
        Client::create(['name' => 'Karim Enterprise']);

        $this->actingAs($this->staff())
            ->get('/admin/clients?q=Rahim')
            ->assertOk()
            ->assertSee('Rahim Traders')
            ->assertDontSee('Karim Enterprise');
    }

    #[Test]
    public function revoking_a_permission_locks_staff_out_without_any_code_change(): void
    {
        /*
         * Proves the permission seam works as designed: restricting staff later
         * is a seeder change, not a refactor.
         */
        $staff = $this->staff();
        $this->actingAs($staff)->get('/admin/partners')->assertOk();

        Role::findByName(User::ROLE_ADMIN)
            ->revokePermissionTo(Permission::ManagePartners->value);

        $this->actingAs($staff->fresh())->get('/admin/partners')->assertForbidden();
    }

    #[Test]
    public function the_super_admin_passes_every_gate_even_with_no_permissions(): void
    {
        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Staff, 'is_active' => true,
        ])->assignRole(User::ROLE_SUPER_ADMIN);

        // super_admin holds no explicit permissions — Gate::before grants all.
        $this->assertCount(0, $owner->getAllPermissions());

        $this->actingAs($owner)->get('/admin/clients')->assertOk();
        $this->actingAs($owner)->get('/admin/partners')->assertOk();
    }
}
