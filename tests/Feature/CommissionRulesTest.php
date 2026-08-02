<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\CommissionBasis;
use App\Models\Client;
use App\Models\Project;
use App\Models\Partner;
use App\Models\StageSet;
use App\Services\CommissionService;
use App\Services\ProjectFinanceService;
use App\Services\StageService;
use App\Support\Money;
use Database\Seeders\StageSetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommissionRulesTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(float $percent = 10, CommissionBasis $basis = CommissionBasis::Collected): Project
    {
        $partner = Partner::create([
            'name' => 'Test Partner',
            'default_commission_percent' => $percent,
        ]);

        $client = Client::create([
            'name' => 'Rahim Traders',
            'partner_id' => $partner->id,
        ]);

        return Project::create([
            'client_id' => $client->id,
            'partner_id' => $partner->id,
            'title' => 'Client portal v2',
            'agreed_amount' => 50000,
            'currency' => 'BDT',
            'commission_basis' => $basis,
        ]);
    }

    #[Test]
    public function it_calculates_due_and_commission_on_collected_money(): void
    {
        $project = $this->makeProject();
        $finance = app(ProjectFinanceService::class);
        $commission = app(CommissionService::class);

        $project->payments()->create([
            'amount' => 20000,
            'paid_at' => now(),
            'method' => 'bkash',
        ]);
        $project->refresh();

        $this->assertSame('50,000.00', $project->agreed_amount->format());
        $this->assertSame('20,000.00', $finance->totalPaid($project)->format());
        $this->assertSame('30,000.00', $finance->amountDue($project)->format());

        // 10% of the 20,000 actually collected — not of the 50,000 agreed.
        $this->assertSame('2,000.00', $commission->owed($project)->format());
        $this->assertSame('2,000.00', $commission->due($project)->format());

        $project->commissionPayouts()->create([
            'partner_id' => $project->partner_id,
            'amount' => 2000,
            'paid_at' => now(),
            'method' => 'bkash',
        ]);
        $project->refresh();

        $this->assertTrue($commission->due($project)->isZero());
    }

    #[Test]
    public function changing_a_partners_default_rate_does_not_alter_existing_projects(): void
    {
        // The single most important rule in the money model.
        $project = $this->makeProject(percent: 10);
        $commission = app(CommissionService::class);

        $project->payments()->create([
            'amount' => 20000, 'paid_at' => now(), 'method' => 'bkash',
        ]);
        $project->refresh();

        $this->assertSame('2,000.00', $commission->owed($project)->format());

        // The partner renegotiates to 25%.
        $project->partner->update(['default_commission_percent' => 25]);
        $project->refresh()->load('partner');

        $this->assertSame('25.00', $project->partner->default_commission_percent);

        // The already-agreed project keeps the terms it was agreed under.
        $this->assertSame('10.00', $project->commission_percent);
        $this->assertSame('2,000.00', $commission->owed($project)->format());
    }

    #[Test]
    public function the_same_partner_can_hold_a_different_deal_per_project(): void
    {
        $project = $this->makeProject(percent: 10);

        $second = Project::create([
            'client_id' => $project->client_id,
            'partner_id' => $project->partner_id,
            'title' => 'Follow-up work',
            'agreed_amount' => 20000,
            'commission_percent' => 5, // negotiated separately
        ]);

        $this->assertSame('10.00', $project->commission_percent);
        $this->assertSame('5.00', $second->commission_percent);
    }

    #[Test]
    public function agreed_basis_owes_full_commission_before_any_payment(): void
    {
        $project = $this->makeProject(percent: 10, basis: CommissionBasis::Agreed);
        $commission = app(CommissionService::class);

        $this->assertSame('5,000.00', $commission->owed($project)->format());
        $this->assertSame('0.00', app(ProjectFinanceService::class)->totalPaid($project)->format());
    }

    #[Test]
    public function overpaying_a_partner_never_creates_a_negative_balance(): void
    {
        $project = $this->makeProject();
        $commission = app(CommissionService::class);

        $project->payments()->create(['amount' => 20000, 'paid_at' => now(), 'method' => 'bkash']);
        $project->commissionPayouts()->create([
            'partner_id' => $project->partner_id,
            'amount' => 3000, // more than the 2,000 owed
            'paid_at' => now(),
            'method' => 'bkash',
        ]);
        $project->refresh();

        $this->assertTrue($commission->due($project)->isZero());
    }

    #[Test]
    public function money_survives_a_round_trip_through_mysql(): void
    {
        $project = $this->makeProject();

        foreach ([0.07, 1.15, 8.29, 1250.5] as $amount) {
            $project->payments()->create([
                'amount' => $amount, 'paid_at' => now(), 'method' => 'cash',
            ]);
        }
        $project->refresh();

        // 0.07 + 1.15 + 8.29 + 1250.50 = 1260.01 exactly.
        $this->assertSame(126_001, app(ProjectFinanceService::class)->totalPaid($project)->minor);
        $this->assertSame('1,260.01', app(ProjectFinanceService::class)->totalPaid($project)->format());
    }

    #[Test]
    public function internal_stages_collapse_for_clients_and_never_leak_their_names(): void
    {
        $this->seed(StageSetSeeder::class);

        $set = StageSet::where('name', 'Web Development')->firstOrFail();
        $project = $this->makeProject();
        $project->update(['stage_set_id' => $set->id]);

        $stages = new StageService;
        $codeReview = $set->stages()->where('name', 'Code review')->firstOrFail();

        $this->assertFalse($codeReview->visible_to_client);

        $stages->moveTo($project, $codeReview);
        $project->refresh();

        $staffView = $stages->timelineFor($project, AccountType::Staff);
        $clientView = $stages->timelineFor($project, AccountType::Client);

        // Staff see the real internal stage as current.
        $this->assertSame('Code review', $staffView->firstWhere('state', 'current')['label']);

        // The client must never see that name anywhere in their timeline...
        $this->assertNotContains('Code review', $clientView->pluck('label')->all());

        // ...and the project must not appear stalled. Their "current" collapses
        // back to the last client-visible stage before the hidden one, so the
        // client sees Development while the team is really on Code review.
        $clientCurrent = $clientView->firstWhere('state', 'current');
        $this->assertNotNull($clientCurrent);
        $this->assertSame('Development', $clientCurrent['label']);

        // The internal stages leave no gap in the client's view.
        $this->assertSame(
            ['Requirements', 'Design', 'Development', 'Your review', 'Delivered'],
            $clientView->pluck('label')->all(),
        );
    }

    #[Test]
    public function renaming_a_stage_does_not_rewrite_history(): void
    {
        $this->seed(StageSetSeeder::class);

        $set = StageSet::where('name', 'Logo & Brand Design')->firstOrFail();
        $project = $this->makeProject();
        $project->update(['stage_set_id' => $set->id]);

        $concepts = $set->stages()->where('name', 'Concepts')->firstOrFail();
        (new StageService)->moveTo($project, $concepts);

        $concepts->update(['name' => 'Initial Drafts']);
        $project->refresh();

        $log = $project->stageLogs()->latest('entered_at')->firstOrFail();

        // The timeline still says what it said at the time.
        $this->assertSame('Concepts', $log->displayName());
        $this->assertSame('Initial Drafts', $log->stage->name);
    }
}
