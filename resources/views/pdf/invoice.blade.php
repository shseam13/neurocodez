{{--
    Invoice PDF — "company pad".

    A deliberately separate stylesheet from the app. No glass, no dark theme,
    no CSS variables: dompdf supports none of it, and a dark purple invoice
    prints badly and wastes the client's ink.

    Constraints this layout is built around:
      * Tables for structure. dompdf has no CSS grid and unreliable flexbox.
      * `position: fixed` repeats on EVERY page — that is how the letterhead
        survives a long invoice.
      * The header and footer stay INSIDE the margins. Consumer printers cannot
        print to the edge, so a full-bleed band would come out clipped with a
        ragged white lip, differently on every printer.
      * Purple appears in exactly four places: two edge rules, the table header,
        and the due block. Restraint is what makes it read as stationery
        rather than a template — and keeps ink cost near zero.
--}}
@php
    use App\Support\AmountInWords;

    $company = $settings;
    $client = $invoice->project->client;
    $currency = $invoice->currency;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page {
            margin: 14mm 14mm 20mm 14mm;
        }

        body {
            font-family: {{ $fontFamily }};
            font-size: 10.5pt;
            line-height: 1.5;
            /* The brand canvas colour reused as ink: prints near-black with a
               faint violet cast, and measures 18.3:1 on white. */
            color: #1e073a;
            margin: 0;
        }

        /* ---- repeating letterhead ------------------------------------- */

        /*
           Offsets are measured from the CONTENT box, not the paper edge, and
           the page margins are 14mm top / 20mm bottom. So:

             top: -6mm    ->  8mm from the top of the sheet
             bottom: -12mm -> 8mm from the bottom of the sheet

           8mm of clearance is deliberate. Consumer inkjet and laser printers
           cannot print the outer ~5mm, so a rule any closer comes out clipped
           with a ragged white lip — differently on every printer. This was
           originally -10mm, which left only 4mm and would have been cut.
        */
        .rule-top,
        .rule-bottom {
            position: fixed;
            left: 0;
            right: 0;
            height: 3mm;
            background: #914ee9;
        }
        .rule-top { top: -6mm; }
        .rule-bottom { bottom: -12mm; }

        .page-footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            font-size: 8pt;
            color: #6b6480;
        }
        .page-footer td { border-top: 0.4pt solid #e9e2f7; padding-top: 2mm; }

        /* ---- masthead -------------------------------------------------- */

        .masthead { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        .masthead td { vertical-align: top; }

        .brand-name {
            font-size: 17pt;
            font-weight: bold;
            letter-spacing: -0.3pt;
            margin: 0;
        }
        .brand-slogan {
            font-size: 7pt;
            letter-spacing: 2pt;
            text-transform: uppercase;
            color: #7c3aed;
            margin: 1mm 0 0;
        }
        .doc-title {
            font-size: 15pt;
            font-weight: bold;
            text-align: right;
            margin: 0;
        }
        .doc-number { font-size: 10pt; text-align: right; color: #6b6480; margin: 1mm 0 0; }

        .hairline { border-bottom: 0.5pt solid #e9e2f7; margin: 0 0 6mm; }

        /* ---- parties --------------------------------------------------- */

        .parties { width: 100%; border-collapse: collapse; margin-bottom: 7mm; }
        .parties td { vertical-align: top; width: 50%; }
        .label {
            font-size: 7.5pt;
            letter-spacing: 0.8pt;
            text-transform: uppercase;
            color: #6b6480;
            margin: 0 0 1.5mm;
        }
        .meta { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        .meta td { padding: 0.6mm 0; }
        .meta .k { color: #6b6480; padding-right: 4mm; }

        /* ---- line items ------------------------------------------------ */

        .items { width: 100%; border-collapse: collapse; }
        .items thead th {
            background: #914ee9;
            color: #ffffff;
            font-size: 8pt;
            letter-spacing: 0.6pt;
            text-transform: uppercase;
            text-align: left;
            padding: 2.4mm 3mm;
        }
        .items tbody td {
            padding: 2.4mm 3mm;
            border-bottom: 0.4pt solid #e9e2f7;
            vertical-align: top;
        }
        .items tbody tr.alt td { background: #faf8fe; }
        .num { text-align: right; }

        /* ---- totals ---------------------------------------------------- */

        .totals-wrap { width: 100%; margin-top: 5mm; }
        .totals { width: 62mm; border-collapse: collapse; font-size: 10pt; }
        .totals td { padding: 1.4mm 3mm; }
        .totals .k { color: #6b6480; }
        .totals .due-row td {
            background: #f1e9fd;
            border-top: 0.8pt solid #914ee9;
            border-bottom: 0.8pt solid #914ee9;
            font-weight: bold;
            font-size: 11pt;
        }

        .in-words { margin-top: 4mm; font-size: 9.5pt; }
        .in-words .k { color: #6b6480; }

        /* ---- foot ------------------------------------------------------ */

        .foot { width: 100%; border-collapse: collapse; margin-top: 12mm; }
        .foot td { vertical-align: bottom; width: 50%; font-size: 9pt; }
        .sign-line {
            border-top: 0.5pt solid #1e073a;
            width: 55mm;
            margin-left: auto;
            padding-top: 1.5mm;
            text-align: center;
            font-size: 8.5pt;
            color: #6b6480;
        }

        /* A totals block or signature split across a page boundary looks like
           a mistake, and invites a client to query the figure. */
        .no-split { page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="rule-top"></div>
    <div class="rule-bottom"></div>

    <table class="page-footer">
        <tr>
            <td>
                {{ $company->name }}@if ($company->website) &nbsp;&middot;&nbsp; {{ $company->website }} @endif
                @if ($company->email) &nbsp;&middot;&nbsp; {{ $company->email }} @endif
                @if ($company->phone) &nbsp;&middot;&nbsp; {{ $company->phone }} @endif
            </td>
            <td style="text-align: right">
                {{-- dompdf substitutes these placeholders at render time. --}}
                Page <span class="pagenum"></span>
            </td>
        </tr>
    </table>

    <table class="masthead">
        <tr>
            <td style="width: 60%">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" height="34" style="margin-bottom: 2mm">
                @endif
                <p class="brand-name">{{ $company->name }}</p>
                @if ($company->slogan)
                    <p class="brand-slogan">{{ $company->slogan }}</p>
                @endif
            </td>
            <td>
                <p class="doc-title">{{ $isReceipt ? 'RECEIPT' : 'INVOICE' }}</p>
                <p class="doc-number">{{ $invoice->number }}</p>
                @if ($invoice->status->value === 'paid')
                    {{-- Status is stated in words, never colour alone: invoices
                         get photocopied and printed in greyscale. --}}
                    <p class="doc-number" style="color:#059669; font-weight:bold">PAID</p>
                @elseif ($invoice->isOverdue())
                    <p class="doc-number" style="color:#be123c; font-weight:bold">OVERDUE</p>
                @endif
            </td>
        </tr>
    </table>

    <div class="hairline"></div>

    <table class="parties">
        <tr>
            <td>
                <p class="label">Bill to</p>
                <strong>{{ $client->company ?: $client->name }}</strong><br>
                @if ($client->company && $client->name !== $client->company)
                    {{ $client->name }}<br>
                @endif
                @if ($client->address){!! nl2br(e($client->address)) !!}<br>@endif
                @if ($client->email){{ $client->email }}<br>@endif
                @if ($client->phone){{ $client->phone }}@endif
            </td>
            <td>
                <table class="meta">
                    <tr>
                        <td class="k">Issued</td>
                        <td>{{ $invoice->issued_at->format('j M Y') }}</td>
                    </tr>
                    @if ($invoice->due_at)
                        <tr>
                            <td class="k">Due</td>
                            <td>{{ $invoice->due_at->format('j M Y') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="k">Project</td>
                        <td>{{ $invoice->project->title }}</td>
                    </tr>
                    <tr>
                        <td class="k">Currency</td>
                        <td>{{ $currency }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        {{-- table-header-group makes dompdf repeat these on every page. --}}
        <thead style="display: table-header-group">
            <tr>
                <th style="width: 8mm">#</th>
                <th>Description</th>
                <th class="num" style="width: 16mm">Qty</th>
                <th class="num" style="width: 26mm">Rate</th>
                <th class="num" style="width: 30mm">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr @class(['alt' => $loop->index % 2 === 1])>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->qty, 2), '0'), '.') }}</td>
                    <td class="num">{{ $item->unit_price->format() }}</td>
                    <td class="num">{{ $item->line_total->format() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-wrap no-split">
        <table class="totals" align="right">
            <tr>
                <td class="k">{{ $invoice->isAdvanceRequest() ? 'Project total' : 'Subtotal' }}</td>
                <td class="num">{{ $invoice->subtotal->format() }}</td>
            </tr>
            @if ($invoice->tax && $invoice->tax->isPositive())
                <tr>
                    <td class="k">Tax</td>
                    <td class="num">{{ $invoice->tax->format() }}</td>
                </tr>
            @endif
            @if ($invoice->isAdvanceRequest())
                {{-- The line items priced the whole engagement; this is the
                     share being asked for now. Both figures are shown so the
                     client can see what they are agreeing to as well as what
                     they are paying today. --}}
                <tr>
                    <td class="k">Advance ({{ App\Support\Percent::format($invoice->advance_percent) }}%)</td>
                    <td class="num">{{ $invoice->billableSubtotal()->format() }}</td>
                </tr>
            @endif
            <tr>
                <td class="k">{{ $invoice->isAdvanceRequest() ? 'Due now' : 'Total' }}</td>
                <td class="num">{{ $invoice->total->format() }}</td>
            </tr>
            @if ($paid->isPositive())
                <tr>
                    <td class="k">{{ $balance->isPositive() ? 'Advance paid' : 'Received' }}</td>
                    <td class="num">&minus; {{ $paid->format() }}</td>
                </tr>
            @endif
            <tr class="due-row">
                <td>{{ $balance->isPositive() ? 'Balance due' : 'Balance' }}</td>
                <td class="num">{{ $currencyLabel }} {{ $balance->floorAtZero()->format() }}</td>
            </tr>
        </table>
        <div style="clear: both"></div>
    </div>

    <p class="in-words no-split">
        <span class="k">In words:</span> {{ AmountInWords::of($balance->floorAtZero()) }}
    </p>

    @if ($invoice->isAdvanceRequest() && $invoice->deferredAmount()->isPositive())
        {{-- Say plainly that more is coming. An advance invoice that shows only
             the smaller figure invites the client to believe that is the whole
             price, and the disagreement surfaces at the worst moment — when the
             work is finished and you are asking for the rest. --}}
        <p class="in-words no-split" style="margin-top:2mm">
            The remaining {{ $currencyLabel }} {{ $invoice->deferredAmount()->format() }}
            will be invoiced on delivery.
        </p>
    @endif

    <table class="foot no-split">
        <tr>
            <td>
                @if ($company->payment_details)
                    <p class="label">Payment details</p>
                    {!! nl2br(e($company->payment_details)) !!}
                @endif
                @if ($invoice->notes)
                    <p class="label" style="margin-top:4mm">Notes</p>
                    {!! nl2br(e($invoice->notes)) !!}
                @endif
            </td>
            <td>
                <div class="sign-line">Authorised signature</div>
            </td>
        </tr>
    </table>
</body>
</html>
