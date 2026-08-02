<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Permission;
use App\Models\Client;
use App\Models\Project;
use App\Models\Partner;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\ProjectFinanceService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAndPayoutTest extends TestCase
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

    private function project(float $percent = 10): Project
    {
        $partner = Partner::create(['name' => 'Karim', 'default_commission_percent' => $percent]);
        $client = Client::create(['name' => 'Rahim Traders', 'partner_id' => $partner->id]);

        return Project::create([
            'client_id' => $client->id, 'partner_id' => $partner->id,
            'title' => 'Portal v2', 'agreed_amount' => 50000,
        ]);
    }

    #[Test]
    public function recording_a_payment_reduces_the_balance(): void
    {
        $project = $this->project();

        $this->actingAs($this->staff())
            ->post("/admin/projects/{$project->id}/payments", [
                'amount' => 20000, 'paid_at' => now()->toDateString(),
                'method' => 'bkash', 'reference' => 'TXN123',
            ])->assertRedirect();

        $finance = app(ProjectFinanceService::class);
        $project->refresh();

        $this->assertSame('20,000.00', $finance->totalPaid($project)->format());
        $this->assertSame('30,000.00', $finance->amountDue($project)->format());
    }

    #[Test]
    public function a_refund_is_recorded_as_a_negative_payment_not_by_editing_history(): void
    {
        $project = $this->project();
        $staff = $this->staff();

        $this->actingAs($staff)->post("/admin/projects/{$project->id}/payments", [
            'amount' => 20000, 'paid_at' => now()->toDateString(), 'method' => 'bkash',
        ]);
        $this->actingAs($staff)->post("/admin/projects/{$project->id}/payments", [
            'amount' => -5000, 'paid_at' => now()->toDateString(), 'method' => 'bkash',
            'note' => 'Partial refund',
        ])->assertRedirect();

        // Both rows survive; the net is what counts.
        $this->assertSame(2, $project->payments()->count());
        $this->assertSame('15,000.00', app(ProjectFinanceService::class)->totalPaid($project->fresh())->format());
    }

    #[Test]
    public function a_zero_payment_is_rejected(): void
    {
        $project = $this->project();

        $this->actingAs($this->staff())
            ->post("/admin/projects/{$project->id}/payments", [
                'amount' => 0, 'paid_at' => now()->toDateString(), 'method' => 'cash',
            ])->assertSessionHasErrors('amount');
    }

    #[Test]
    public function overpayment_is_allowed_but_flagged_in_the_message(): void
    {
        $project = $this->project();

        $this->actingAs($this->staff())
            ->post("/admin/projects/{$project->id}/payments", [
                'amount' => 60000, 'paid_at' => now()->toDateString(), 'method' => 'bank',
            ])->assertRedirect();

        $this->assertStringContainsString('overpaid', session('status'));
        $this->assertStringContainsString('10,000.00', session('status'));
    }

    #[Test]
    public function commission_accrues_as_the_client_pays_and_settles_on_payout(): void
    {
        $project = $this->project(percent: 10);
        $staff = $this->staff();
        $commission = app(CommissionService::class);

        $this->actingAs($staff)->post("/admin/projects/{$project->id}/payments", [
            'amount' => 20000, 'paid_at' => now()->toDateString(), 'method' => 'bkash',
        ]);

        $this->assertSame('2,000.00', $commission->due($project->fresh())->format());

        $this->actingAs($staff)->post("/admin/projects/{$project->id}/payouts", [
            'amount' => 2000, 'paid_at' => now()->toDateString(), 'method' => 'bkash',
        ])->assertRedirect();

        $this->assertTrue($commission->due($project->fresh())->isZero());
        $this->assertStringContainsString('settled', session('status'));
    }

    #[Test]
    public function a_payout_cannot_be_recorded_on_a_project_with_no_partner(): void
    {
        $project = Project::create([
            'client_id' => Client::create(['name' => 'Direct Ltd'])->id,
            'title' => 'Direct job', 'agreed_amount' => 10000,
        ]);

        $this->actingAs($this->staff())
            ->post("/admin/projects/{$project->id}/payouts", [
                'amount' => 500, 'paid_at' => now()->toDateString(), 'method' => 'cash',
            ])->assertNotFound();
    }

    #[Test]
    public function a_payment_cannot_be_deleted_through_a_different_project(): void
    {
        $a = $this->project();
        $b = Project::create([
            'client_id' => $a->client_id, 'title' => 'Other', 'agreed_amount' => 1000,
        ]);

        $payment = $a->payments()->create(['amount' => 500, 'paid_at' => now(), 'method' => 'cash']);

        $this->actingAs($this->staff(User::ROLE_SUPER_ADMIN))
            ->delete("/admin/projects/{$b->id}/payments/{$payment->id}")
            ->assertNotFound();

        $this->assertNotSoftDeleted('payments', ['id' => $payment->id]);
    }

    #[Test]
    public function a_plain_admin_cannot_delete_a_payment(): void
    {
        // A payment row is the record that money changed hands.
        $project = $this->project();
        $payment = $project->payments()->create(['amount' => 500, 'paid_at' => now(), 'method' => 'cash']);

        $this->actingAs($this->staff(User::ROLE_ADMIN))
            ->delete("/admin/projects/{$project->id}/payments/{$payment->id}")
            ->assertForbidden();
    }

    #[Test]
    public function the_payouts_page_lists_what_is_owed(): void
    {
        $project = $this->project();
        $project->payments()->create(['amount' => 20000, 'paid_at' => now(), 'method' => 'bkash']);

        $this->actingAs($this->staff())
            ->get('/admin/payouts')
            ->assertOk()
            ->assertSee('Karim')
            ->assertSee('2,000.00');
    }

    #[Test]
    public function revoking_view_commissions_hides_the_payouts_page(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff)->get('/admin/payouts')->assertOk();

        Role::findByName(User::ROLE_ADMIN)->revokePermissionTo(Permission::ViewCommissions->value);

        $this->actingAs($staff->fresh())->get('/admin/payouts')->assertForbidden();
    }

    #[Test]
    public function money_records_are_written_to_the_audit_log(): void
    {
        $project = $this->project();

        $this->actingAs($this->staff())->post("/admin/projects/{$project->id}/payments", [
            'amount' => 20000, 'paid_at' => now()->toDateString(), 'method' => 'bkash',
        ]);

        // Audit scope is money records only — enough to reconstruct a disputed
        // figure without logging every click.
        $this->assertDatabaseHas('activity_log', ['log_name' => 'money', 'subject_type' => \App\Models\Payment::class]);
    }
}
