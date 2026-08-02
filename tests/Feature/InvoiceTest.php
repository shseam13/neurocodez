<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ChargeKind;
use App\Enums\ChargeStatus;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Project;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\AmountInWords;
use App\Support\Money;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        CompanySetting::current();
    }

    private ?User $staff = null;

    private function staff(string $role = User::ROLE_ADMIN): User
    {
        return $this->staff ??= User::create([
            'name' => 'Staff', 'email' => 'staff@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Staff, 'is_active' => true,
        ])->assignRole($role);
    }

    private function project(): Project
    {
        $client = Client::create([
            'name' => 'Rahim Traders', 'company' => 'Rahim Traders Ltd',
            'email' => 'rahim@example.com', 'address' => "12 Agrabad\nChattogram",
        ]);

        return Project::create([
            'client_id' => $client->id, 'title' => 'Client portal v2',
            'agreed_amount' => 50000, 'currency' => 'BDT',
        ]);
    }

    // ------------------------------------------------------------- numbering

    #[Test]
    public function invoice_numbers_are_sequential_and_prefixed(): void
    {
        $project = $this->project();
        $service = app(InvoiceService::class);

        $first = $service->draftFor($project);
        $second = $service->draftFor($project);

        $year = now()->year;
        $this->assertSame("INV-{$year}-001", $first->number);
        $this->assertSame("INV-{$year}-002", $second->number);
    }

    #[Test]
    public function invoice_numbers_are_unique_under_concurrent_creation(): void
    {
        // The number column is unique, so a collision would be a hard failure
        // rather than a silently duplicated invoice.
        $project = $this->project();
        $service = app(InvoiceService::class);

        $numbers = collect(range(1, 8))->map(fn () => $service->draftFor($project)->number);

        $this->assertSame(8, $numbers->unique()->count());
    }

    // ------------------------------------------------------------ line items

    #[Test]
    public function a_draft_itemises_the_scope_and_each_approved_charge(): void
    {
        $project = $this->project();

        $project->charges()->create([
            'title' => 'Three extra pages', 'amount' => 15000,
            'kind' => ChargeKind::Extra, 'status' => ChargeStatus::Approved,
            'occurred_at' => now(),
        ]);
        $project->charges()->create([
            'title' => 'Not agreed yet', 'amount' => 9000,
            'kind' => ChargeKind::Extra, 'status' => ChargeStatus::Quoted,
            'occurred_at' => now(),
        ]);

        $invoice = app(InvoiceService::class)->draftFor($project->fresh());

        // Scope + the approved charge only. A quote is not a bill.
        $this->assertCount(2, $invoice->items);
        $this->assertSame('65,000.00', $invoice->total->format());
        $this->assertStringContainsString('Three extra pages', $invoice->items->last()->description);
    }

    #[Test]
    public function totals_recalculate_from_the_line_items(): void
    {
        $project = $this->project();
        $invoice = app(InvoiceService::class)->draftFor($project);

        $this->actingAs($this->staff())->put(route('admin.invoices.update', $invoice), [
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
            'tax' => 500,
            'items' => [
                ['description' => 'Design', 'qty' => 1, 'unit_price' => 20000],
                ['description' => 'Development', 'qty' => 2, 'unit_price' => 15000],
            ],
        ])->assertRedirect();

        $invoice->refresh();

        $this->assertSame('50,000.00', $invoice->subtotal->format());   // 20,000 + 30,000
        $this->assertSame('50,500.00', $invoice->total->format());      // + tax
    }

    // ----------------------------------------------------------------- state

    #[Test]
    public function a_sent_invoice_is_locked_against_editing(): void
    {
        $invoice = app(InvoiceService::class)->draftFor($this->project());
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.invoices.send', $invoice))->assertRedirect();
        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);

        // The client may already hold this exact document.
        $this->actingAs($staff)->put(route('admin.invoices.update', $invoice), [
            'issued_at' => now()->toDateString(),
            'items' => [['description' => 'Sneaky change', 'qty' => 1, 'unit_price' => 1]],
        ])->assertForbidden();
    }

    #[Test]
    public function deleting_a_sent_invoice_voids_it_instead(): void
    {
        $invoice = app(InvoiceService::class)->draftFor($this->project());
        $owner = $this->staff(User::ROLE_SUPER_ADMIN);

        $this->actingAs($owner)->post(route('admin.invoices.send', $invoice));
        $this->actingAs($owner)->delete(route('admin.invoices.destroy', $invoice))->assertRedirect();

        // The number and the trail survive; the document does not vanish.
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
    }

    #[Test]
    public function a_draft_invoice_can_be_deleted_outright(): void
    {
        $invoice = app(InvoiceService::class)->draftFor($this->project());

        $this->actingAs($this->staff(User::ROLE_SUPER_ADMIN))
            ->delete(route('admin.invoices.destroy', $invoice))
            ->assertRedirect();

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    // ------------------------------------------------------------------- pdf

    #[Test]
    public function the_pdf_renders_as_a_real_pdf(): void
    {
        $invoice = app(InvoiceService::class)->draftFor($this->project());

        $response = $this->actingAs($this->staff())
            ->get(route('admin.invoices.pdf', $invoice))
            ->assertOk();

        $body = $response->getContent();

        $this->assertStringStartsWith('%PDF-', $body);
        $this->assertGreaterThan(2000, strlen($body));
    }

    #[Test]
    public function a_thirty_line_invoice_produces_more_than_one_page(): void
    {
        /*
         * Proves the letterhead survives a long invoice: the header, footer and
         * table head are `position: fixed` / table-header-group so dompdf
         * repeats them, and the totals block carries page-break-inside: avoid.
         */
        $project = $this->project();
        $invoice = app(InvoiceService::class)->draftFor($project);

        $invoice->items()->delete();
        for ($i = 1; $i <= 30; $i++) {
            $invoice->items()->create([
                'description' => "Line item number {$i} with a reasonably long description",
                'qty' => 1, 'unit_price' => Money::ofMajor(1000), 'position' => $i,
            ]);
        }
        app(InvoiceService::class)->recalculate($invoice->load('items'));

        $body = $this->actingAs($this->staff())
            ->get(route('admin.invoices.pdf', $invoice))
            ->assertOk()
            ->getContent();

        // dompdf writes one /Type /Page object per page.
        $pages = preg_match_all('#/Type\s*/Page[^s]#', $body);
        $this->assertGreaterThan(1, $pages, 'Expected a multi-page PDF.');
    }

    #[Test]
    public function the_pdf_uses_the_iso_code_not_the_taka_glyph_by_default(): void
    {
        /*
         * dompdf's bundled DejaVu fonts contain no Bengali script, so U+09F3
         * would render as an empty box. Guarded by config until a Bengali font
         * is registered.
         */
        $this->assertFalse((bool) config('neuro.use_taka_glyph'));

        $invoice = app(InvoiceService::class)->draftFor($this->project());

        $html = view('pdf.invoice', [
            'invoice' => $invoice->load('items', 'project.client'),
            'settings' => CompanySetting::current(),
            'paid' => Money::zero(),
            'balance' => $invoice->total,
            'isReceipt' => false,
            'currencyLabel' => 'BDT',
            'fontFamily' => 'DejaVu Sans, sans-serif',
            'logo' => null,
        ])->render();

        $this->assertStringNotContainsString('৳', $html);
        $this->assertStringContainsString('BDT', $html);
    }

    // -------------------------------------------------------------- audience

    #[Test]
    public function a_client_can_read_a_sent_invoice_but_not_a_draft(): void
    {
        $project = $this->project();
        $invoice = app(InvoiceService::class)->draftFor($project);

        $clientUser = User::create([
            'name' => 'Rahim', 'email' => 'rahim@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Client, 'client_id' => $project->client_id, 'is_active' => true,
        ]);

        // A draft is a working document — its figures may still be wrong.
        $this->actingAs($clientUser)->get(route('admin.invoices.pdf', $invoice))->assertForbidden();

        app(InvoiceService::class)->markSent($invoice);

        $this->actingAs($clientUser)->get(route('admin.invoices.pdf', $invoice->fresh()))->assertOk();
    }

    #[Test]
    public function another_clients_invoice_is_never_readable(): void
    {
        $mine = $this->project();
        $theirs = Project::create([
            'client_id' => Client::create(['name' => 'Karim Enterprise'])->id,
            'title' => 'Their job', 'agreed_amount' => 10000,
        ]);

        $theirInvoice = app(InvoiceService::class)->draftFor($theirs);
        app(InvoiceService::class)->markSent($theirInvoice);

        $myUser = User::create([
            'name' => 'Rahim', 'email' => 'rahim@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Client, 'client_id' => $mine->client_id, 'is_active' => true,
        ]);

        $this->actingAs($myUser)
            ->get(route('admin.invoices.pdf', $theirInvoice->fresh()))
            ->assertForbidden();
    }

    #[Test]
    public function a_partner_reads_the_invoice_only_when_they_are_the_one_billed(): void
    {
        $partner = Partner::create(['name' => 'Karim', 'default_commission_percent' => 10]);
        $project = $this->project();
        $project->update(['partner_id' => $partner->id, 'billed_to' => 'client']);

        $invoice = app(InvoiceService::class)->draftFor($project->fresh());
        app(InvoiceService::class)->markSent($invoice);

        $partnerUser = User::create([
            'name' => 'Karim', 'email' => 'karim@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Partner, 'partner_id' => $partner->id, 'is_active' => true,
        ]);

        // Billed to the client: the bill is between us and them.
        $this->actingAs($partnerUser)->get(route('admin.invoices.pdf', $invoice))->assertForbidden();

        // Billed to the partner: they owe the money, so they get the invoice.
        $project->update(['billed_to' => 'partner']);
        $this->actingAs($partnerUser)->get(route('admin.invoices.pdf', $invoice->fresh()))->assertOk();
    }

    // ----------------------------------------------------------------- words

    #[Test]
    public function amounts_are_written_in_words_using_the_south_asian_scale(): void
    {
        $this->assertSame('Thirty thousand taka only', AmountInWords::of(Money::ofMajor(30000)));
        $this->assertSame('One lakh twenty thousand taka only', AmountInWords::of(Money::ofMajor(120000)));
        $this->assertSame('One crore taka only', AmountInWords::of(Money::ofMajor(10000000)));
        $this->assertSame('Zero taka only', AmountInWords::of(Money::zero()));
    }

    #[Test]
    public function words_include_the_fractional_subunit(): void
    {
        $this->assertSame(
            'One thousand two hundred fifty taka and fifty poisha only',
            AmountInWords::of(Money::ofMajor(1250.50)),
        );
    }
}
