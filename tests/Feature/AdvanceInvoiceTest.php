<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Project;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Advance-request invoices, and recording what arrives against them.
 */
class AdvanceInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private ?User $staffUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        CompanySetting::current();
    }

    private function staff(): User
    {
        return $this->staffUser ??= User::create([
            'name' => 'Staff', 'email' => 'staff@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Staff, 'is_active' => true,
        ])->assignRole(User::ROLE_SUPER_ADMIN);
    }

    private function project(): Project
    {
        $client = Client::create(['name' => 'Rahim Traders', 'email' => 'rahim@example.com']);

        return Project::create([
            'client_id' => $client->id, 'title' => 'Portfolio site',
            'agreed_amount' => 30000, 'currency' => 'BDT',
        ]);
    }

    private function draftWithOneLine(Project $project, int $amount = 30000)
    {
        $invoice = app(InvoiceService::class)->draftFor($project);
        $invoice->items()->delete();
        $invoice->items()->create([
            'description' => 'Portfolio site', 'qty' => 1,
            'unit_price' => $amount, 'line_total' => $amount, 'position' => 0,
        ]);

        return app(InvoiceService::class)->recalculate($invoice->load('items'));
    }

    #[Test]
    public function without_an_advance_the_total_is_the_whole_subtotal(): void
    {
        $invoice = $this->draftWithOneLine($this->project());

        $this->assertFalse($invoice->isAdvanceRequest());
        $this->assertSame('30,000.00', $invoice->subtotal->format());
        $this->assertSame('30,000.00', $invoice->total->format());
        $this->assertTrue($invoice->deferredAmount()->isZero());
    }

    #[Test]
    public function an_advance_bills_the_percentage_while_the_subtotal_keeps_the_full_scope(): void
    {
        $project = $this->project();
        $invoice = $this->draftWithOneLine($project);

        $this->actingAs($this->staff())->put(route('admin.invoices.update', $invoice), [
            'issued_at' => now()->toDateString(),
            'advance_percent' => '50',
            'items' => [['description' => 'Portfolio site', 'qty' => '1', 'unit_price' => '30000']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $invoice->refresh();

        // The client must still see what the whole engagement costs.
        $this->assertSame('30,000.00', $invoice->subtotal->format());
        $this->assertSame('15,000.00', $invoice->total->format());
        $this->assertSame('15,000.00', $invoice->deferredAmount()->format());
        $this->assertTrue($invoice->isAdvanceRequest());
    }

    #[Test]
    public function tax_is_added_on_top_of_the_advance_not_the_full_scope(): void
    {
        $project = $this->project();
        $invoice = $this->draftWithOneLine($project);

        $this->actingAs($this->staff())->put(route('admin.invoices.update', $invoice), [
            'issued_at' => now()->toDateString(),
            'advance_percent' => '50',
            'tax' => '500',
            'items' => [['description' => 'Portfolio site', 'qty' => '1', 'unit_price' => '30000']],
        ])->assertRedirect();

        // 15,000 advance + 500 tax, not 30,000 + 500.
        $this->assertSame('15,500.00', $invoice->refresh()->total->format());
    }

    /** 100% is not an advance, it is the whole invoice. */
    #[Test]
    public function an_advance_of_the_entire_amount_is_rejected(): void
    {
        $invoice = $this->draftWithOneLine($this->project());

        $this->actingAs($this->staff())->put(route('admin.invoices.update', $invoice), [
            'issued_at' => now()->toDateString(),
            'advance_percent' => '100',
            'items' => [['description' => 'Portfolio site', 'qty' => '1', 'unit_price' => '30000']],
        ])->assertSessionHasErrors('advance_percent');
    }

    #[Test]
    public function an_odd_percentage_still_reconciles_to_the_subtotal(): void
    {
        $project = $this->project();
        $invoice = $this->draftWithOneLine($project, 33333);

        $this->actingAs($this->staff())->put(route('admin.invoices.update', $invoice), [
            'issued_at' => now()->toDateString(),
            'advance_percent' => '33.33',
            'items' => [['description' => 'Portfolio site', 'qty' => '1', 'unit_price' => '33333']],
        ])->assertRedirect();

        $invoice->refresh();

        // Advance + deferred must equal the subtotal exactly. Rounding that
        // loses or invents a poisha here would never reconcile.
        $this->assertSame(
            $invoice->subtotal->minor,
            $invoice->billableSubtotal()->minor + $invoice->deferredAmount()->minor,
        );
    }

    #[Test]
    public function a_payment_can_be_recorded_from_the_invoice_screen(): void
    {
        $project = $this->project();
        $invoice = $this->draftWithOneLine($project);

        $this->actingAs($this->staff())
            ->get(route('admin.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('Record a payment')
            ->assertSee(route('admin.projects.payments.store', $project), escape: false);

        $this->actingAs($this->staff())
            ->post(route('admin.projects.payments.store', $project), [
                'amount' => '15000',
                'paid_at' => now()->toDateString(),
                'method' => 'bkash',
                'reference' => 'TRX8891',
            ])->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'project_id' => $project->id,
            'reference' => 'TRX8891',
        ]);

        // 30,000 agreed less a 15,000 advance.
        $this->assertSame('15,000.00', app(\App\Services\ProjectFinanceService::class)
            ->amountDue($project->fresh())->format());
    }
    /**
     * The document must not contradict itself.
     *
     * Printing "Due now 15,000" and then a project-wide "Balance due 25,000"
     * on the same page leaves the client with no idea which figure to pay.
     */
    #[Test]
    public function an_advance_invoice_shows_what_is_owed_on_itself_not_the_project(): void
    {
        $project = $this->project();
        $invoice = $this->draftWithOneLine($project);
        $invoice->forceFill(["advance_percent" => 50])->save();
        app(InvoiceService::class)->recalculate($invoice->fresh()->load("items"));

        $project->payments()->create(["amount" => 5000, "paid_at" => now(), "method" => "bkash"]);

        $invoice->refresh();
        $service = app(InvoiceService::class);

        // 15,000 asked for, 5,000 received.
        $this->assertSame("10,000.00", $service->balanceFor($invoice)->format());

        // The project figure is still available, and still different.
        $this->assertSame("25,000.00", $service->projectBalance($invoice)->format());
    }

    #[Test]
    public function an_ordinary_invoice_still_reports_the_project_balance(): void
    {
        $project = $this->project();
        $invoice = $this->draftWithOneLine($project);

        $project->payments()->create(["amount" => 5000, "paid_at" => now(), "method" => "bkash"]);

        $service = app(InvoiceService::class);

        $this->assertSame(
            $service->projectBalance($invoice->refresh())->minor,
            $service->balanceFor($invoice)->minor,
        );
    }
}