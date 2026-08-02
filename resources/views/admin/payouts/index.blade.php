<x-layouts.admin title="Commissions payable" heading="Commissions payable">
    <div class="glass mb-5 p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total outstanding</p>
        <p class="nums mt-2 text-3xl font-bold {{ $totalDue->isPositive() ? 'text-overdue' : 'text-paid' }}">
            <span class="text-base font-medium text-ink-soft">{{ $totalDue->currency }}</span>
            {{ $totalDue->format() }}
        </p>
        <p class="mt-1 text-xs text-ink-muted">across every partner</p>
    </div>

    @forelse ($rows as $row)
        @php($partner = $row['partner'])
        @php($summary = $row['summary'])

        <section class="surface mb-5 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                <div>
                    <h2 class="font-semibold text-ink">
                        <a href="{{ route('admin.partners.show', $partner) }}" class="hover:text-brand-text">
                            {{ $partner->name }}
                        </a>
                    </h2>
                    <p class="nums mt-0.5 text-xs text-ink-muted">
                        {{ $summary['total_owed']->format() }} earned &middot;
                        {{ $summary['total_paid']->format() }} paid
                    </p>
                </div>

                <span class="nums text-lg font-bold {{ $summary['total_due']->isPositive() ? 'text-overdue' : 'text-paid' }}">
                    {{ $summary['total_due']->formatWithCurrency() }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-2.5 font-semibold">Project</th>
                            <th class="px-5 py-2.5 font-semibold">Client</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Rate</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Earned</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Paid</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Due</th>
                            <th class="px-5 py-2.5"><span class="sr-only">Pay</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['projects'] as $line)
                            <tr class="border-b border-line last:border-0">
                                <td class="px-5 py-2.5">
                                    <a href="{{ route('admin.projects.show', $line['project']) }}"
                                       class="font-medium text-ink hover:text-brand-text">{{ $line['project']->title }}</a>
                                </td>
                                <td class="px-5 py-2.5 text-ink-soft">{{ $line['project']->client->name }}</td>
                                <td class="nums px-5 py-2.5 text-right text-ink-soft">
                                    {{ \App\Support\Percent::withSign($line['percent']) }}
                                </td>
                                <td class="nums px-5 py-2.5 text-right text-ink-soft">{{ $line['owed']->format() }}</td>
                                <td class="nums px-5 py-2.5 text-right text-paid">{{ $line['paid']->format() }}</td>
                                <td class="nums px-5 py-2.5 text-right font-semibold {{ $line['due']->isPositive() ? 'text-overdue' : 'text-ink-muted' }}">
                                    {{ $line['due']->format() }}
                                </td>
                                <td class="px-5 py-2.5 text-right">
                                    @if ($line['due']->isPositive())
                                        @can('create', App\Models\CommissionPayout::class)
                                            {{-- Straight to the project, where the payout form
                                                 is pre-filled with exactly this amount. --}}
                                            <a href="{{ route('admin.projects.show', $line['project']) }}"
                                               class="text-xs font-medium text-brand-text hover:underline">Pay</a>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="surface p-12 text-center">
            <x-brand.mark :size="40" class="mx-auto text-brand opacity-40" />
            <h2 class="mt-5 font-semibold text-ink">No commissions yet</h2>
            <p class="mt-2 text-sm text-ink-soft">
                Once a referred project takes payment, what you owe will appear here.
            </p>
        </div>
    @endforelse
</x-layouts.admin>
