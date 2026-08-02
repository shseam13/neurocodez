<x-layouts.admin title="Invoices" heading="Invoices">
    <div class="mb-5 flex flex-wrap items-center gap-2">
        @foreach (['' => 'All', 'draft' => 'Drafts', 'sent' => 'Sent', 'paid' => 'Paid', 'void' => 'Void'] as $value => $label)
            <a href="{{ route('admin.invoices.index', $value !== '' ? ['status' => $value] : []) }}"
               @class([
                   'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                   'bg-brand text-white' => $status === $value,
                   'glass-flat text-ink-soft hover:text-ink' => $status !== $value,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="surface overflow-hidden">
        @if ($invoices->isEmpty())
            <div class="p-12 text-center">
                <x-brand.mark :size="40" class="mx-auto text-brand opacity-40" />
                <h2 class="mt-5 font-semibold text-ink">No invoices yet</h2>
                <p class="mt-2 text-sm text-ink-soft">
                    Raise one from a project — its scope and approved extras become the line items.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Number</th>
                            <th class="px-5 py-3 font-semibold">Client</th>
                            <th class="px-5 py-3 font-semibold">Project</th>
                            <th class="px-5 py-3 font-semibold">Issued</th>
                            <th class="px-5 py-3 text-right font-semibold">Total</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr class="border-b border-line last:border-0">
                                <td class="nums px-5 py-3">
                                    <a href="{{ route('admin.invoices.edit', $invoice) }}"
                                       class="font-medium text-ink hover:text-brand-text">{{ $invoice->number }}</a>
                                </td>
                                <td class="px-5 py-3 text-ink-soft">{{ $invoice->project->client->name }}</td>
                                <td class="px-5 py-3 text-ink-soft">{{ $invoice->project->title }}</td>
                                <td class="px-5 py-3 text-ink-soft">{{ $invoice->issued_at->format('j M Y') }}</td>
                                <td class="nums px-5 py-3 text-right font-semibold text-ink">
                                    {{ $invoice->total->format() }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="badge badge-{{ $invoice->isOverdue() ? 'overdue' : $invoice->status->tone() }}">
                                        {{ $invoice->isOverdue() ? 'Overdue' : $invoice->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.invoices.pdf', $invoice) }}" target="_blank" rel="noopener"
                                       class="text-xs font-medium text-brand-text hover:underline">PDF</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($invoices->hasPages())
        <div class="mt-5">{{ $invoices->links() }}</div>
    @endif
</x-layouts.admin>
