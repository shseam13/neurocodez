<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceService;
use App\Services\ProjectFinanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly ProjectFinanceService $finance,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $status = $request->string('status')->toString();

        return view('admin.invoices.index', [
            'invoices' => Invoice::query()
                ->with('project.client')
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->orderByDesc('issued_at')
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    /** Draft an invoice from a project's scope plus its approved extras. */
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);

        $invoice = $this->invoices->draftFor($project, $request->user());

        return redirect()
            ->route('admin.invoices.edit', $invoice)
            ->with('status', "Draft {$invoice->number} created from this project.");
    }

    public function edit(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        return view('admin.invoices.edit', [
            'invoice' => $invoice->load('items', 'project.client'),
            'paid' => $this->finance->totalPaid($invoice->project),
            'balance' => $this->invoices->projectBalance($invoice),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        $data = $request->validate([
            'issued_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $invoice->fill([
            'issued_at' => $data['issued_at'],
            'due_at' => $data['due_at'] ?? null,
            'tax' => $data['tax'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ])->save();

        // Replaced wholesale rather than diffed: a draft is a working document,
        // and reconciling reordered rows adds risk for no benefit.
        $invoice->items()->delete();

        foreach (array_values($data['items']) as $position => $row) {
            $invoice->items()->create([
                'description' => $row['description'],
                'qty' => $row['qty'],
                'unit_price' => $row['unit_price'],
                'position' => $position,
            ]);
        }

        $this->invoices->recalculate($invoice->load('items'));

        return back()->with('status', 'Invoice saved.');
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('send', $invoice);

        $this->invoices->markSent($invoice);

        return back()->with('status',
            "{$invoice->number} marked as sent. It is now locked and visible to the client.");
    }

    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('send', $invoice);

        $data = $request->validate([
            'status' => ['required', 'in:draft,sent,paid,void'],
        ]);

        $invoice->forceFill($data)->save();

        return back()->with('status', 'Invoice status updated.');
    }

    /** The company-pad PDF. Streamed inline so it previews in the browser. */
    public function pdf(Invoice $invoice, Request $request): Response
    {
        Gate::authorize('view', $invoice);

        $pdf = $this->render($invoice);

        return $request->boolean('download')
            ? $pdf->download($invoice->number.'.pdf')
            : $pdf->stream($invoice->number.'.pdf');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        // A sent invoice is a document the client holds. Voiding leaves the
        // number and the trail intact; deleting would make it vanish.
        if ($invoice->status->isLocked()) {
            $invoice->forceFill(['status' => InvoiceStatus::Void])->save();

            return back()->with('status', "{$invoice->number} voided rather than deleted — the client already has it.");
        }

        $number = $invoice->number;
        $invoice->delete();

        return redirect()->route('admin.invoices.index')->with('status', "Draft {$number} deleted.");
    }

    private function render(Invoice $invoice)
    {
        $invoice->load('items', 'project.client');
        $settings = CompanySetting::current();

        /*
         * The ৳ glyph (U+09F3) is NOT safe by default.
         *
         * dompdf's bundled DejaVu fonts carry no Bengali script, so it renders
         * as an empty box. Until a Bengali-capable font is registered, the ISO
         * code is used — a broken currency symbol on a client invoice is worse
         * than a slightly plainer one.
         */
        $useGlyph = (bool) config('neuro.use_taka_glyph', false);

        return Pdf::setPaper(config('neuro.invoice.page_size', 'a4'))
            ->setOption(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true])
            ->loadView('pdf.invoice', [
                'invoice' => $invoice,
                'settings' => $settings,
                'paid' => $this->finance->totalPaid($invoice->project),
                'balance' => $this->invoices->projectBalance($invoice),
                'isReceipt' => false,
                'currencyLabel' => $useGlyph && $invoice->currency === 'BDT' ? '৳' : $invoice->currency,
                'fontFamily' => $useGlyph ? "'Noto Sans Bengali', DejaVu Sans, sans-serif" : 'DejaVu Sans, sans-serif',
                'logo' => $this->logoDataUri(),
            ]);
    }

    /**
     * The mark as a base64 data URI.
     *
     * Embedded rather than linked because remote loading is disabled — and even
     * a local path would break the moment files move to object storage.
     */
    private function logoDataUri(): ?string
    {
        $path = public_path('brand/logo-mark-purple-128.png');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }
}
