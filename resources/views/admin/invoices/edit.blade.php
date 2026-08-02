@php($locked = $invoice->status->isLocked())

<x-layouts.admin :title="$invoice->number" :heading="$invoice->number">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.invoices.index') }}" class="text-sm text-ink-muted hover:text-ink">&larr; All invoices</a>

        <span class="badge badge-{{ $invoice->isOverdue() ? 'overdue' : $invoice->status->tone() }}">
            {{ $invoice->isOverdue() ? 'Overdue' : $invoice->status->label() }}
        </span>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.invoices.pdf', $invoice) }}" target="_blank" rel="noopener"
               class="glass-flat rounded-xl px-4 py-2 text-sm font-medium text-ink">Preview PDF</a>
            <a href="{{ route('admin.invoices.pdf', ['invoice' => $invoice, 'download' => 1]) }}"
               class="glass-flat rounded-xl px-4 py-2 text-sm font-medium text-ink">Download</a>

            @if (! $locked)
                <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}">
                    @csrf
                    <button type="submit"
                            class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                        Mark as sent
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($locked)
        {{-- Editing a document the client already holds is how disputes start. --}}
        <div class="mb-5 rounded-xl border border-info/30 bg-info/5 p-4">
            <p class="text-sm font-medium text-ink">This invoice is locked</p>
            <p class="mt-1 text-sm text-ink-soft">
                It has been sent, so the client may already be holding this exact document.
                Change the status below, or void it and raise a new one — but the figures stay as issued.
            </p>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_300px]">
        <form method="POST" action="{{ route('admin.invoices.update', $invoice) }}" class="space-y-5">
            @csrf @method('PUT')

            <div class="surface p-6">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field name="issued_at" label="Issued" type="date" required
                                :value="old('issued_at', $invoice->issued_at->toDateString())" />
                    <x-ui.field name="due_at" label="Due" type="date"
                                :value="old('due_at', $invoice->due_at?->toDateString())" />
                </div>
            </div>

            <div class="surface overflow-hidden">
                <div class="border-b border-line px-5 py-3.5">
                    <h2 class="font-semibold text-ink">Line items</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="items-table">
                        <thead>
                            <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
                                <th class="px-4 py-2.5 font-semibold">Description</th>
                                <th class="px-4 py-2.5 font-semibold" style="width:6rem">Qty</th>
                                <th class="px-4 py-2.5 font-semibold" style="width:9rem">Rate</th>
                                <th class="px-4 py-2.5" style="width:3rem"><span class="sr-only">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $i => $item)
                                <tr class="border-b border-line last:border-0">
                                    <td class="px-4 py-2">
                                        <input type="text" name="items[{{ $i }}][description]"
                                               value="{{ $item->description }}" required @disabled($locked)
                                               class="w-full rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-brand focus:outline-none disabled:opacity-60">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" name="items[{{ $i }}][qty]" step="0.01" min="0.01"
                                               value="{{ rtrim(rtrim((string) $item->qty, '0'), '.') ?: 1 }}" required @disabled($locked)
                                               class="nums w-full rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-brand focus:outline-none disabled:opacity-60">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" name="items[{{ $i }}][unit_price]" step="0.01" min="0"
                                               value="{{ $item->unit_price->toMajor() }}" required @disabled($locked)
                                               class="nums w-full rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-brand focus:outline-none disabled:opacity-60">
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        @unless ($locked)
                                            <button type="button" data-remove-row
                                                    class="text-ink-muted hover:text-overdue">&times;</button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @unless ($locked)
                    <div class="border-t border-line bg-surface-alt px-5 py-3">
                        <button type="button" id="add-row"
                                class="text-sm font-medium text-brand-text hover:underline">+ Add line</button>
                    </div>
                @endunless
            </div>

            <div class="surface p-6">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="tax" class="mb-1.5 block text-sm font-medium text-ink">Tax</label>
                        <input id="tax" name="tax" type="number" step="0.01" min="0" @disabled($locked)
                               value="{{ old('tax', $invoice->tax?->toMajor() ?? 0) }}"
                               class="nums w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none disabled:opacity-60">
                    </div>

                    <div>
                        <label for="advance_percent" class="mb-1.5 block text-sm font-medium text-ink">
                            Advance requested
                        </label>
                        <div class="flex items-center gap-2">
                            <input id="advance_percent" name="advance_percent" type="number" step="0.01"
                                   min="0.01" max="99.99" placeholder="—" @disabled($locked)
                                   value="{{ old('advance_percent', $invoice->advance_percent !== null ? \App\Support\Percent::format($invoice->advance_percent) : '') }}"
                                   class="nums w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none disabled:opacity-60">
                            <span class="text-sm text-ink-muted">%</span>
                        </div>
                        <p class="mt-1.5 text-xs text-ink-muted">
                            Leave blank to bill the whole amount. Set it to ask for part up front —
                            the line items still show the full scope, and the rest is invoiced later.
                        </p>
                        @error('advance_percent')
                            <p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-5">
                    <x-ui.field name="notes" label="Notes on the invoice" type="textarea"
                                :value="old('notes', $invoice->notes)"
                                help="Printed under the totals. Payment terms, thanks, anything the client should read." />
                </div>
            </div>

            @unless ($locked)
                <button type="submit"
                        class="rounded-xl bg-brand px-6 py-2.5 font-semibold text-white transition hover:bg-brand-hover">
                    Save invoice
                </button>
            @endunless
        </form>

        <aside class="space-y-5">
            <div class="surface p-5">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">Totals</h2>
                <dl class="nums space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">{{ $invoice->isAdvanceRequest() ? 'Full scope' : 'Subtotal' }}</dt>
                        <dd class="font-medium text-ink">{{ $invoice->subtotal?->format() ?? '0.00' }}</dd>
                    </div>

                    @if ($invoice->isAdvanceRequest())
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">
                                Advance {{ \App\Support\Percent::format($invoice->advance_percent) }}%
                            </dt>
                            <dd class="font-medium text-ink">{{ $invoice->billableSubtotal()->format() }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Invoiced later</dt>
                            <dd class="text-ink-muted">{{ $invoice->deferredAmount()->format() }}</dd>
                        </div>
                    @endif

                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">Tax</dt>
                        <dd class="font-medium text-ink">{{ $invoice->tax?->format() ?? '0.00' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">{{ $invoice->isAdvanceRequest() ? 'Due now' : 'Invoice total' }}</dt>
                        <dd class="font-semibold text-ink">{{ $invoice->total?->format() ?? '0.00' }}</dd>
                    </div>
                </dl>

                <div class="mt-4 border-t border-line pt-4">
                    <dl class="nums space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">Received on project</dt>
                            <dd class="text-paid">{{ $paid->format() }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">
                                {{ $invoice->isAdvanceRequest() ? 'Due on this invoice' : 'Project balance' }}
                            </dt>
                            <dd class="font-semibold {{ $balance->isPositive() ? 'text-overdue' : 'text-ink-muted' }}">
                                {{ $balance->format() }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-ink-muted">
                        @if ($invoice->isAdvanceRequest())
                            This invoice asks for the advance only. The project balance is
                            {{ $invoice->project->currency ?? 'BDT' }}
                            {{ app(\App\Services\InvoiceService::class)->projectBalance($invoice)->format() }}.
                        @else
                            {{-- Payments are recorded against the project, not an
                                 individual invoice, so this is project-wide. --}}
                            Payments are tracked per project, so this balance covers the whole project.
                        @endif
                    </p>
                </div>
            </div>

            {{-- Recording a payment from here rather than only on the project
                 page: an advance almost always arrives in response to an
                 invoice, and having to navigate away to log it is how payments
                 end up unrecorded. It still writes to the project, which stays
                 the single source of truth for money received. --}}
            @can('create', \App\Models\Payment::class)
                <div class="surface p-5">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Record a payment</h2>

                    <form method="POST" action="{{ route('admin.projects.payments.store', $invoice->project) }}"
                          class="space-y-2.5">
                        @csrf

                        <div>
                            <label for="pay_amount" class="sr-only">Amount</label>
                            <input id="pay_amount" name="amount" type="number" step="0.01" required
                                   placeholder="Amount"
                                   value="{{ old('amount', $balance->isPositive() ? $invoice->total?->toMajor() : null) }}"
                                   class="nums w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <input name="paid_at" type="date" required value="{{ old('paid_at', now()->toDateString()) }}"
                                   class="nums w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">

                            <select name="method" required
                                    class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">
                                @foreach (\App\Enums\PaymentMethod::cases() as $case)
                                    <option value="{{ $case->value }}" @selected(old('method') === $case->value)>{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <input name="reference" type="text" maxlength="190" placeholder="Reference (optional)"
                               value="{{ old('reference') }}"
                               class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">

                        <button type="submit" data-busy-text="Saving…"
                                class="w-full rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                            Add payment
                        </button>

                        @error('amount')<p class="text-xs font-medium text-overdue">{{ $message }}</p>@enderror
                    </form>

                    @if ($invoice->project->payments->isNotEmpty())
                        <div class="mt-4 space-y-1.5 border-t border-line pt-3">
                            @foreach ($invoice->project->payments->sortByDesc('paid_at')->take(5) as $payment)
                                <div class="flex justify-between gap-3 text-xs">
                                    <span class="text-ink-muted">
                                        {{ $payment->paid_at->format('j M') }} &middot; {{ $payment->method->label() }}
                                    </span>
                                    <span class="nums {{ $payment->amount->isNegative() ? 'text-overdue' : 'text-paid' }}">
                                        {{ $payment->amount->format() }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endcan

            <div class="surface p-5">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Status</h2>
                <form method="POST" action="{{ route('admin.invoices.status', $invoice) }}" class="flex gap-2">
                    @csrf @method('PUT')
                    <select name="status"
                            class="flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">
                        @foreach (\App\Enums\InvoiceStatus::cases() as $case)
                            <option value="{{ $case->value }}" @selected($invoice->status === $case)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="glass-flat rounded-lg px-3 py-2 text-sm font-medium text-ink">Set</button>
                </form>
            </div>

            <div class="surface p-5">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Project</h2>
                <a href="{{ route('admin.projects.show', $invoice->project) }}"
                   class="text-sm font-medium text-brand-text hover:underline">{{ $invoice->project->title }}</a>
                <p class="mt-1 text-xs text-ink-muted">{{ $invoice->project->client->name }}</p>
            </div>
        </aside>
    </div>

    @unless ($locked)
        <script>
            // Minimal row cloning — an invoice editor does not justify a framework.
            (function () {
                const body = document.querySelector('#items-table tbody');
                const add = document.querySelector('#add-row');
                if (!body || !add) return;

                let next = {{ $invoice->items->count() }};

                add.addEventListener('click', () => {
                    const row = document.createElement('tr');
                    row.className = 'border-b border-line last:border-0';
                    row.innerHTML = `
                        <td class="px-4 py-2"><input type="text" name="items[${next}][description]" required
                            class="w-full rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-brand focus:outline-none"></td>
                        <td class="px-4 py-2"><input type="number" name="items[${next}][qty]" step="0.01" min="0.01" value="1" required
                            class="nums w-full rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-brand focus:outline-none"></td>
                        <td class="px-4 py-2"><input type="number" name="items[${next}][unit_price]" step="0.01" min="0" value="0" required
                            class="nums w-full rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-brand focus:outline-none"></td>
                        <td class="px-4 py-2 text-center"><button type="button" data-remove-row class="text-ink-muted hover:text-overdue">&times;</button></td>`;
                    body.appendChild(row);
                    next++;
                });

                body.addEventListener('click', (event) => {
                    if (!event.target.closest('[data-remove-row]')) return;
                    // Keep at least one row — the request validates items as non-empty.
                    if (body.rows.length > 1) event.target.closest('tr').remove();
                });
            })();
        </script>
    @endunless
</x-layouts.admin>
