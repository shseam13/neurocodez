<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ChargeKind;
use App\Enums\ChargeStatus;
use App\Enums\CommissionBasis;
use App\Models\Client;
use App\Models\Project;
use App\Models\Partner;
use App\Services\CommissionService;
use App\Services\ProjectFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectChargesTest extends TestCase
{
    use RefreshDatabase;

    private function project(float $percent = 10, CommissionBasis $basis = CommissionBasis::Collected): Project
    {
        $partner = Partner::create(['name' => 'Karim', 'default_commission_percent' => $percent]);
        $client = Client::create(['name' => 'Rahim Traders', 'partner_id' => $partner->id]);

        return Project::create([
            'client_id' => $client->id,
            'partner_id' => $partner->id,
            'title' => 'Client portal v2',
            'agreed_amount' => 50000,
            'commission_basis' => $basis,
        ]);
    }

    private function finance(): ProjectFinanceService
    {
        return app(ProjectFinanceService::class);
    }

    private function commission(): CommissionService
    {
        return app(CommissionService::class);
    }

    #[Test]
    public function the_original_agreed_amount_is_never_changed_by_extra_work(): void
    {
        $project = $this->project();

        $project->charges()->create([
            'title' => 'Three extra pages',
            'amount' => 15000,
            'kind' => ChargeKind::Extra,
            'status' => ChargeStatus::Approved,
            'occurred_at' => now(),
        ]);
        $project->refresh();

        // What was originally agreed stays visible and unchanged.
        $this->assertSame('50,000.00', $this->finance()->originalScope($project)->format());
        $this->assertSame('15,000.00', $this->finance()->approvedExtras($project)->format());
        $this->assertSame('65,000.00', $this->finance()->contractValue($project)->format());
    }

    #[Test]
    public function a_quoted_charge_does_not_inflate_what_the_client_owes(): void
    {
        $project = $this->project();

        $project->charges()->create([
            'title' => 'Possible extra work',
            'amount' => 25000,
            'kind' => ChargeKind::Extra,
            'status' => ChargeStatus::Quoted,
            'occurred_at' => now(),
        ]);
        $project->refresh();

        $this->assertSame('50,000.00', $this->finance()->contractValue($project)->format());
        $this->assertSame('50,000.00', $this->finance()->amountDue($project)->format());
    }

    #[Test]
    public function approving_a_quote_adds_it_to_the_balance_and_stamps_the_time(): void
    {
        $project = $this->project();

        $charge = $project->charges()->create([
            'title' => 'Extra pages', 'amount' => 10000,
            'kind' => ChargeKind::Extra, 'status' => ChargeStatus::Quoted,
            'occurred_at' => now(),
        ]);

        $this->assertNull($charge->approved_at);

        $charge->approve();
        $project->refresh();

        $this->assertNotNull($charge->fresh()->approved_at);
        $this->assertSame('60,000.00', $this->finance()->contractValue($project)->format());
    }

    #[Test]
    public function commission_is_earned_on_extra_work_by_default(): void
    {
        $project = $this->project(percent: 10, basis: CommissionBasis::Agreed);

        $project->charges()->create([
            'title' => 'Extra pages', 'amount' => 20000,
            'kind' => ChargeKind::Extra, 'status' => ChargeStatus::Approved,
            'occurred_at' => now(),
        ]);
        $project->refresh();

        // 10% of 70,000, not of the original 50,000.
        $this->assertSame('7,000.00', $this->commission()->owed($project)->format());
    }

    #[Test]
    public function a_charge_can_be_excluded_from_commission(): void
    {
        $project = $this->project(percent: 10, basis: CommissionBasis::Agreed);

        $project->charges()->create([
            'title' => 'Work the partner had no part in',
            'amount' => 20000,
            'kind' => ChargeKind::Maintenance,
            'status' => ChargeStatus::Approved,
            'commission_applies' => false,
            'occurred_at' => now(),
        ]);
        $project->refresh();

        $summary = $this->commission()->summarise($project);

        $this->assertSame('5,000.00', $summary['owed']->format());          // 10% of 50,000
        $this->assertSame('20,000.00', $summary['excluded']->format());     // explainable to the partner
        $this->assertSame('70,000.00', $this->finance()->contractValue($project)->format());
    }

    #[Test]
    public function collected_basis_apportions_payments_pro_rata_when_a_charge_is_excluded(): void
    {
        /*
         * Contract 100,000 of which 50,000 earns commission => ratio 0.5.
         * Client pays 40,000, so 20,000 counts toward commission => 10% = 2,000.
         *
         * Payments are not tagged to line items, so pro-rata is the only
         * defensible apportionment.
         */
        $project = $this->project(percent: 10, basis: CommissionBasis::Collected);

        $project->charges()->create([
            'title' => 'Non-commissionable maintenance', 'amount' => 50000,
            'kind' => ChargeKind::Maintenance, 'status' => ChargeStatus::Approved,
            'commission_applies' => false, 'occurred_at' => now(),
        ]);

        $project->payments()->create(['amount' => 40000, 'paid_at' => now(), 'method' => 'bkash']);
        $project->refresh();

        $this->assertSame('100,000.00', $this->finance()->contractValue($project)->format());
        $this->assertSame('20,000.00', $this->commission()->base($project)->format());
        $this->assertSame('2,000.00', $this->commission()->owed($project)->format());
    }

    #[Test]
    public function with_no_exclusions_the_collected_basis_does_not_apportion_at_all(): void
    {
        // The common case must stay exact — no ratio, no rounding.
        $project = $this->project(percent: 10, basis: CommissionBasis::Collected);

        $project->charges()->create([
            'title' => 'Extra pages', 'amount' => 33333,
            'kind' => ChargeKind::Extra, 'status' => ChargeStatus::Approved,
            'occurred_at' => now(),
        ]);
        $project->payments()->create(['amount' => 12345.67, 'paid_at' => now(), 'method' => 'cash']);
        $project->refresh();

        $this->assertSame(
            $this->finance()->totalPaid($project)->minor,
            $this->commission()->base($project)->minor,
        );
    }

    #[Test]
    public function a_follow_up_project_links_back_but_keeps_its_own_terms(): void
    {
        $original = $this->project(percent: 10);

        $maintenance = Project::create([
            'client_id' => $original->client_id,
            'parent_id' => $original->id,
            'partner_id' => $original->partner_id,
            'title' => 'Maintenance 2026',
            'agreed_amount' => 24000,
            'commission_percent' => 5, // negotiated separately
        ]);

        $this->assertTrue($original->followUps->contains($maintenance));
        $this->assertSame($original->id, $maintenance->parent->id);
        $this->assertSame('10.00', $original->commission_percent);
        $this->assertSame('5.00', $maintenance->commission_percent);
    }

    #[Test]
    public function the_retainer_generator_creates_one_charge_per_month_and_never_double_bills(): void
    {
        $project = $this->project();
        $project->update([
            'is_retainer' => true,
            'retainer_amount' => 5000,
            'retainer_day' => 1,
            'retainer_starts_on' => '2026-01-01',
        ]);

        $this->artisan('retainers:generate', ['--date' => '2026-03-05'])->assertSuccessful();
        $this->artisan('retainers:generate', ['--date' => '2026-03-20'])->assertSuccessful();

        // Two runs in the same month, one charge.
        $this->assertSame(1, $project->charges()->where('kind', ChargeKind::RetainerCycle)->count());

        $this->artisan('retainers:generate', ['--date' => '2026-04-02'])->assertSuccessful();
        $this->assertSame(2, $project->charges()->where('kind', ChargeKind::RetainerCycle)->count());

        $project->refresh();
        $this->assertSame('10,000.00', $this->finance()->approvedExtras($project)->format());
    }

    #[Test]
    public function a_retainer_stops_billing_after_its_end_date(): void
    {
        $project = $this->project();
        $project->update([
            'is_retainer' => true,
            'retainer_amount' => 5000,
            'retainer_day' => 1,
            'retainer_starts_on' => '2026-01-01',
            'retainer_ends_on' => '2026-02-28',
        ]);

        $this->artisan('retainers:generate', ['--date' => '2026-02-10'])->assertSuccessful();
        $this->artisan('retainers:generate', ['--date' => '2026-03-10'])->assertSuccessful();

        // February billed, March not — no one has to remember to switch it off.
        $this->assertSame(1, $project->charges()->where('kind', ChargeKind::RetainerCycle)->count());
    }

    #[Test]
    public function a_retainer_does_not_bill_before_its_billing_day(): void
    {
        $project = $this->project();
        $project->update([
            'is_retainer' => true,
            'retainer_amount' => 5000,
            'retainer_day' => 15,
            'retainer_starts_on' => '2026-01-01',
        ]);

        $this->artisan('retainers:generate', ['--date' => '2026-03-10'])->assertSuccessful();
        $this->assertSame(0, $project->charges()->count());

        $this->artisan('retainers:generate', ['--date' => '2026-03-15'])->assertSuccessful();
        $this->assertSame(1, $project->charges()->count());
    }
}
