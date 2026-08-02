<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ChargeKind;
use App\Enums\ChargeStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Partner;
use App\Models\StageSet;
use App\Models\User;
use App\Services\ProjectFinanceService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StageSetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function staff(string $role = User::ROLE_ADMIN): User
    {
        return User::create([
            'name' => 'Staff', 'email' => 'staff@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Staff, 'is_active' => true,
        ])->assignRole($role);
    }

    private function client(): Client
    {
        return Client::create(['name' => 'Rahim Traders']);
    }

    #[Test]
    public function creating_a_project_snapshots_the_partners_current_rate(): void
    {
        $partner = Partner::create(['name' => 'Karim', 'default_commission_percent' => 12]);
        $client = $this->client();

        $this->actingAs($this->staff())->post('/admin/projects', [
            'client_id' => $client->id,
            'partner_id' => $partner->id,
            'title' => 'Portal v2',
            'agreed_amount' => 50000,
            'currency' => 'BDT',
            'commission_basis' => 'collected',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertSame('12.00', Project::firstOrFail()->commission_percent);
    }

    #[Test]
    public function a_project_starts_on_the_first_stage_of_its_set(): void
    {
        // A brand-new project with a blank timeline looks broken.
        $this->seed(StageSetSeeder::class);
        $set = StageSet::where('name', 'Web Development')->firstOrFail();

        $this->actingAs($this->staff())->post('/admin/projects', [
            'client_id' => $this->client()->id,
            'title' => 'Portal v2',
            'agreed_amount' => 50000,
            'currency' => 'BDT',
            'commission_basis' => 'collected',
            'status' => 'active',
            'stage_set_id' => $set->id,
        ])->assertRedirect();

        $project = Project::firstOrFail();

        $this->assertNotNull($project->current_stage_id);
        $this->assertSame('Requirements', $project->currentStage->name);
        $this->assertSame(1, $project->stageLogs()->count());
    }

    /**
     * Post ids as strings, the way a browser does.
     *
     * Every value in a real form submission arrives as a string. The test above
     * passes $set->id as an int, so it never reproduced the mismatch that broke
     * project creation in production: the model kept "1" from the request while
     * the stage came back from the database as 1, and StageService's strict
     * comparison rejected it with "That stage belongs to a different stage
     * set." Creating a project through the form was impossible; every test
     * passed.
     */
    #[Test]
    public function a_project_can_be_created_from_real_form_input(): void
    {
        $this->seed(StageSetSeeder::class);
        $set = StageSet::where('name', 'Web Development')->firstOrFail();

        $this->actingAs($this->staff())->post('/admin/projects', [
            'client_id' => (string) $this->client()->id,
            'title' => 'Portal v2',
            'agreed_amount' => '50000',
            'currency' => 'BDT',
            'commission_basis' => 'collected',
            'status' => 'active',
            'stage_set_id' => (string) $set->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $project = Project::firstOrFail();

        $this->assertSame('Requirements', $project->currentStage->name);
        $this->assertSame(1, $project->stageLogs()->count());
        $this->assertIsInt($project->stage_set_id);
        $this->assertIsInt($project->client_id);
    }

    #[Test]
    public function adding_an_approved_charge_raises_the_balance_but_not_the_agreed_amount(): void
    {
        $project = Project::create([
            'client_id' => $this->client()->id, 'title' => 'Portal', 'agreed_amount' => 50000,
        ]);

        $this->actingAs($this->staff())
            ->post("/admin/projects/{$project->id}/charges", [
                'title' => 'Three extra pages',
                'amount' => 15000,
                'kind' => ChargeKind::Extra->value,
                'status' => ChargeStatus::Approved->value,
                'occurred_at' => now()->toDateString(),
                'commission_applies' => '1',
            ])->assertRedirect();

        $project->refresh();
        $finance = app(ProjectFinanceService::class);

        $this->assertSame('50,000.00', $project->agreed_amount->format());
        $this->assertSame('65,000.00', $finance->contractValue($project)->format());
    }

    #[Test]
    public function a_quoted_charge_can_be_approved_from_the_project_page(): void
    {
        $staff = $this->staff();
        $project = Project::create([
            'client_id' => $this->client()->id, 'title' => 'Portal', 'agreed_amount' => 50000,
        ]);

        $this->actingAs($staff)->post("/admin/projects/{$project->id}/charges", [
            'title' => 'Maybe extra', 'amount' => 10000,
            'kind' => ChargeKind::Extra->value, 'status' => ChargeStatus::Quoted->value,
            'occurred_at' => now()->toDateString(),
        ]);

        $charge = $project->charges()->firstOrFail();
        $this->assertSame('50,000.00', app(ProjectFinanceService::class)->contractValue($project->fresh())->format());

        $this->actingAs($staff)
            ->post("/admin/projects/{$project->id}/charges/{$charge->id}/approve")
            ->assertRedirect();

        $this->assertSame('60,000.00', app(ProjectFinanceService::class)->contractValue($project->fresh())->format());
    }

    #[Test]
    public function a_charge_cannot_be_approved_through_a_different_project(): void
    {
        // Guards against swapping the project id in the URL.
        $a = Project::create(['client_id' => $this->client()->id, 'title' => 'A', 'agreed_amount' => 1000]);
        $b = Project::create(['client_id' => $this->client()->id, 'title' => 'B', 'agreed_amount' => 1000]);

        $charge = $a->charges()->create([
            'title' => 'Extra', 'amount' => 500, 'kind' => ChargeKind::Extra,
            'status' => ChargeStatus::Quoted, 'occurred_at' => now(),
        ]);

        $this->actingAs($this->staff())
            ->post("/admin/projects/{$b->id}/charges/{$charge->id}/approve")
            ->assertNotFound();

        $this->assertSame(ChargeStatus::Quoted, $charge->fresh()->status);
    }

    #[Test]
    public function a_retainer_charge_is_cancelled_rather_than_deleted(): void
    {
        $project = Project::create([
            'client_id' => $this->client()->id, 'title' => 'Care plan', 'agreed_amount' => 0,
        ]);

        $charge = $project->charges()->create([
            'title' => 'Retainer — March 2026', 'amount' => 5000,
            'kind' => ChargeKind::RetainerCycle, 'status' => ChargeStatus::Approved,
            'occurred_at' => '2026-03-01', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31',
        ]);

        $this->actingAs($this->staff())
            ->delete("/admin/projects/{$project->id}/charges/{$charge->id}")
            ->assertRedirect();

        // The month stays visible as cancelled instead of vanishing.
        $this->assertNotSoftDeleted('project_charges', ['id' => $charge->id]);
        $this->assertSame(ChargeStatus::Cancelled, $charge->fresh()->status);
    }

    #[Test]
    public function a_project_with_payments_cannot_be_deleted(): void
    {
        $project = Project::create([
            'client_id' => $this->client()->id, 'title' => 'Portal', 'agreed_amount' => 50000,
        ]);
        $project->payments()->create(['amount' => 1000, 'paid_at' => now(), 'method' => 'cash']);

        $this->actingAs($this->staff(User::ROLE_SUPER_ADMIN))
            ->delete("/admin/projects/{$project->id}")
            ->assertSessionHasErrors('project');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    #[Test]
    public function moving_a_stage_records_it_in_the_history(): void
    {
        $this->seed(StageSetSeeder::class);
        $set = StageSet::where('name', 'Web Development')->firstOrFail();
        $project = Project::create([
            'client_id' => $this->client()->id, 'title' => 'Portal',
            'agreed_amount' => 1000, 'stage_set_id' => $set->id,
        ]);

        $design = $set->stages()->where('name', 'Design')->firstOrFail();

        $this->actingAs($this->staff())
            ->post("/admin/projects/{$project->id}/stage", ['stage_id' => $design->id, 'note' => 'Kickoff done'])
            ->assertRedirect();

        $log = $project->stageLogs()->latest('entered_at')->firstOrFail();

        $this->assertSame('Design', $log->stage_name_snapshot);
        $this->assertSame('Kickoff done', $log->note);
        $this->assertSame($design->id, $project->fresh()->current_stage_id);
    }

    #[Test]
    public function a_stage_from_another_set_is_rejected(): void
    {
        $this->seed(StageSetSeeder::class);
        $web = StageSet::where('name', 'Web Development')->firstOrFail();
        $logo = StageSet::where('name', 'Logo & Brand Design')->firstOrFail();

        $project = Project::create([
            'client_id' => $this->client()->id, 'title' => 'Portal',
            'agreed_amount' => 1000, 'stage_set_id' => $web->id,
        ]);

        $this->actingAs($this->staff())
            ->post("/admin/projects/{$project->id}/stage", ['stage_id' => $logo->stages()->first()->id])
            ->assertSessionHasErrors('stage_id');
    }

    #[Test]
    public function portal_accounts_cannot_reach_project_admin(): void
    {
        $client = $this->client();
        $portalUser = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Client, 'client_id' => $client->id, 'is_active' => true,
        ]);

        $this->actingAs($portalUser)->get('/admin/projects')->assertRedirect('/portal');
    }
}
