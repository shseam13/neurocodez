<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ChargeKind;
use App\Enums\ChargeStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The dashboards were previously only asserted as redirect *targets*, never
 * rendered — which let an ambiguous-column SQL error reach the browser. These
 * tests load each one for real.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function staff(): User
    {
        return User::create([
            'name' => 'Owner', 'email' => 'owner@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Staff, 'is_active' => true,
        ])->assignRole(User::ROLE_SUPER_ADMIN);
    }

    private function seedMoney(): Project
    {
        $partner = Partner::create(['name' => 'Karim', 'default_commission_percent' => 10]);
        $client = Client::create(['name' => 'Rahim Traders', 'partner_id' => $partner->id]);

        $project = Project::create([
            'client_id' => $client->id,
            'partner_id' => $partner->id,
            'title' => 'Portal v2',
            'agreed_amount' => 50000,
            'deadline' => now()->subDays(3),
        ]);

        $project->payments()->create(['amount' => 20000, 'paid_at' => now(), 'method' => 'bkash']);

        // The charges join is what made `status` ambiguous against projects.status.
        $project->charges()->create([
            'title' => 'Extra pages', 'amount' => 15000,
            'kind' => ChargeKind::Extra, 'status' => ChargeStatus::Approved,
            'occurred_at' => now(),
        ]);
        $project->charges()->create([
            'title' => 'Maybe more', 'amount' => 9000,
            'kind' => ChargeKind::Extra, 'status' => ChargeStatus::Quoted,
            'occurred_at' => now(),
        ]);

        return $project;
    }

    #[Test]
    public function the_staff_dashboard_renders_with_charges_present(): void
    {
        $this->seedMoney();

        $this->actingAs($this->staff())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Receivable')
            ->assertSee('Commissions payable');
    }

    #[Test]
    public function company_totals_include_approved_extras_but_not_quotes(): void
    {
        $this->seedMoney();

        $totals = app(\App\Services\ProjectFinanceService::class)->companyTotals();

        // 50,000 agreed + 15,000 approved extra. The 9,000 quote is excluded.
        $this->assertSame('65,000.00', $totals['receivable']->format());
        $this->assertSame('20,000.00', $totals['collected']->format());
        $this->assertSame('45,000.00', $totals['outstanding']->format());
        $this->assertSame(1, $totals['overdue_count']);
    }

    #[Test]
    public function the_client_portal_renders(): void
    {
        $project = $this->seedMoney();

        $user = User::create([
            'name' => 'Client', 'email' => 'client@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Client, 'client_id' => $project->client_id, 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/portal')
            ->assertOk()
            ->assertSee('Portal v2')
            // A client must never see commission wording anywhere.
            ->assertDontSee('commission', false)
            ->assertDontSee('Karim');
    }

    #[Test]
    public function the_partner_portal_renders(): void
    {
        $project = $this->seedMoney();

        $user = User::create([
            'name' => 'Karim', 'email' => 'karim@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Partner, 'partner_id' => $project->partner_id, 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/partner')
            ->assertOk()
            ->assertSee('Portal v2')
            ->assertSee('10%'); // the snapshotted rate, formatted correctly
    }

    #[Test]
    public function the_admin_list_pages_render(): void
    {
        $this->seedMoney();
        $staff = $this->staff();

        foreach (['/admin/clients', '/admin/partners', '/admin/projects'] as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
    }

    #[Test]
    public function the_project_detail_page_renders(): void
    {
        $project = $this->seedMoney();

        $this->actingAs($this->staff())
            ->get("/admin/projects/{$project->id}")
            ->assertOk()
            ->assertSee('Extra work')
            ->assertSee('Original scope');
    }
}
