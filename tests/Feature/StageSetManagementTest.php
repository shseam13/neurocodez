<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Client;
use App\Models\Project;
use App\Models\StageSet;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StageSetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StageSetManagementTest extends TestCase
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

    #[Test]
    public function a_set_can_be_created_and_stages_added_in_order(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post('/admin/stage-sets', ['name' => 'App Build', 'is_active' => '1'])
            ->assertRedirect();

        $set = StageSet::where('name', 'App Build')->firstOrFail();

        foreach (['Discovery', 'Build', 'Launch'] as $name) {
            $this->actingAs($staff)->post("/admin/stage-sets/{$set->id}/stages", [
                'name' => $name, 'visible_to_client' => '1',
            ])->assertRedirect();
        }

        $this->assertSame(
            ['Discovery', 'Build', 'Launch'],
            $set->stages()->pluck('name')->all(),
        );
        $this->assertSame([0, 1, 2], $set->stages()->pluck('position')->all());
    }

    #[Test]
    public function stages_can_be_reordered(): void
    {
        $staff = $this->staff();
        $set = StageSet::create(['name' => 'Test']);

        foreach (['A', 'B', 'C'] as $name) {
            $this->actingAs($staff)->post("/admin/stage-sets/{$set->id}/stages", ['name' => $name]);
        }

        $b = $set->stages()->where('name', 'B')->firstOrFail();

        $this->actingAs($staff)
            ->post("/admin/stage-sets/{$set->id}/stages/{$b->id}/move", ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(['B', 'A', 'C'], $set->stages()->pluck('name')->all());
        // Positions stay contiguous after a swap.
        $this->assertSame([0, 1, 2], $set->stages()->pluck('position')->all());
    }

    #[Test]
    public function renaming_a_stage_leaves_existing_project_history_intact(): void
    {
        $this->seed(StageSetSeeder::class);
        $staff = $this->staff();

        $set = StageSet::where('name', 'Web Development')->firstOrFail();
        $stage = $set->stages()->where('name', 'Design')->firstOrFail();

        $project = Project::create([
            'client_id' => Client::create(['name' => 'Acme'])->id,
            'title' => 'Site', 'agreed_amount' => 1000, 'stage_set_id' => $set->id,
        ]);
        app(\App\Services\StageService::class)->moveTo($project, $stage);

        $this->actingAs($staff)->put("/admin/stage-sets/{$set->id}/stages/{$stage->id}", [
            'name' => 'Visual Design', 'visible_to_client' => '1',
        ])->assertRedirect();

        $this->assertSame('Visual Design', $stage->fresh()->name);
        // The log still says what it said at the time.
        $this->assertSame('Design', $project->stageLogs()->latest('entered_at')->first()->stage_name_snapshot);
    }

    #[Test]
    public function a_stage_in_use_as_a_projects_current_stage_cannot_be_removed(): void
    {
        $this->seed(StageSetSeeder::class);
        $set = StageSet::where('name', 'Web Development')->firstOrFail();
        $stage = $set->stages()->where('name', 'Design')->firstOrFail();

        $project = Project::create([
            'client_id' => Client::create(['name' => 'Acme'])->id,
            'title' => 'Site', 'agreed_amount' => 1000, 'stage_set_id' => $set->id,
        ]);
        app(\App\Services\StageService::class)->moveTo($project, $stage);

        $this->actingAs($this->staff())
            ->delete("/admin/stage-sets/{$set->id}/stages/{$stage->id}")
            ->assertSessionHasErrors('stage');

        $this->assertNotSoftDeleted('stages', ['id' => $stage->id]);
    }

    #[Test]
    public function duplicating_a_set_produces_an_independent_copy(): void
    {
        $this->seed(StageSetSeeder::class);
        $set = StageSet::where('name', 'Web Development')->firstOrFail();

        $this->actingAs($this->staff())
            ->post("/admin/stage-sets/{$set->id}/duplicate")
            ->assertRedirect();

        $copy = StageSet::where('name', 'Web Development (copy)')->firstOrFail();

        $this->assertSame($set->stages()->count(), $copy->stages()->count());
        $this->assertFalse($copy->is_default);

        // Editing the copy must not touch the original.
        $copy->stages()->first()->update(['name' => 'Changed']);
        $this->assertSame('Requirements', $set->stages()->first()->fresh()->name);
    }

    #[Test]
    public function only_one_set_can_be_the_default(): void
    {
        $this->seed(StageSetSeeder::class);
        $logo = StageSet::where('name', 'Logo & Brand Design')->firstOrFail();

        $this->actingAs($this->staff())
            ->post("/admin/stage-sets/{$logo->id}/default")
            ->assertRedirect();

        $this->assertTrue($logo->fresh()->is_default);
        $this->assertSame(1, StageSet::where('is_default', true)->count());
    }

    #[Test]
    public function a_set_used_by_projects_cannot_be_deleted(): void
    {
        $this->seed(StageSetSeeder::class);
        $set = StageSet::where('name', 'Web Development')->firstOrFail();

        Project::create([
            'client_id' => Client::create(['name' => 'Acme'])->id,
            'title' => 'Site', 'agreed_amount' => 1000, 'stage_set_id' => $set->id,
        ]);

        $this->actingAs($this->staff(User::ROLE_SUPER_ADMIN))
            ->delete("/admin/stage-sets/{$set->id}")
            ->assertSessionHasErrors('set');

        $this->assertNotSoftDeleted('stage_sets', ['id' => $set->id]);
    }

    #[Test]
    public function the_stage_set_pages_render(): void
    {
        $this->seed(StageSetSeeder::class);
        $staff = $this->staff();
        $set = StageSet::first();

        $this->actingAs($staff)->get('/admin/stage-sets')->assertOk()->assertSee('Web Development');
        $this->actingAs($staff)->get("/admin/stage-sets/{$set->id}/edit")->assertOk()->assertSee('Stages');
    }

    #[Test]
    public function portal_accounts_cannot_reach_stage_set_management(): void
    {
        $user = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Client, 'client_id' => Client::create(['name' => 'A'])->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/stage-sets')->assertRedirect('/portal');
    }
}
