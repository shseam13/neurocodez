<x-layouts.admin :title="$partner->name" :heading="$partner->name">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.partners.index') }}" class="text-sm text-ink-muted hover:text-ink">&larr; All partners</a>

        @can('update', $partner)
            <a href="{{ route('admin.partners.edit', $partner) }}"
               class="ml-auto glass-flat rounded-xl px-4 py-2 text-sm font-medium text-ink">Edit</a>
        @endcan
    </div>

    <section class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Earned', $summary['total_owed'], 'ink', 'commission accrued'],
            ['Paid out', $summary['total_paid'], 'paid', 'already settled'],
            ['You owe', $summary['total_due'], 'overdue', 'outstanding'],
        ] as [$label, $amount, $tone, $meta])
            <div class="glass p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</p>
                <p class="nums mt-2 text-2xl font-bold text-{{ $tone }}">
                    <span class="text-sm font-medium text-ink-soft">{{ $amount->currency }}</span>
                    {{ $amount->format() }}
                </p>
                <p class="mt-1 text-xs text-ink-muted">{{ $meta }}</p>
            </div>
        @endforeach
    </section>

    <section class="surface mt-5 overflow-hidden">
        <div class="border-b border-line px-5 py-3.5">
            <h2 class="font-semibold text-ink">Commission by project</h2>
        </div>

        @if (count($summary['projects']) === 0)
            <p class="px-5 py-10 text-center text-sm text-ink-muted">
                No projects referred by {{ $partner->name }} yet.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Project</th>
                            <th class="px-5 py-3 font-semibold">Client</th>
                            <th class="px-5 py-3 font-semibold">Basis</th>
                            <th class="px-5 py-3 text-right font-semibold">Rate</th>
                            <th class="px-5 py-3 text-right font-semibold">Earned</th>
                            <th class="px-5 py-3 text-right font-semibold">Paid</th>
                            <th class="px-5 py-3 text-right font-semibold">Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['projects'] as $row)
                            <tr class="border-b border-line last:border-0">
                                <td class="px-5 py-3 font-medium text-ink">{{ $row['project']->title }}</td>
                                <td class="px-5 py-3 text-ink-soft">{{ $row['project']->client->name }}</td>
                                <td class="px-5 py-3 text-xs text-ink-muted">
                                    {{ $row['basis']->label() }}
                                </td>
                                {{-- Rate comes from the project's own snapshot, never
                                     from the partner's current default. --}}
                                <td class="nums px-5 py-3 text-right text-ink-soft">
                                    {{ \App\Support\Percent::withSign($row['percent']) }}
                                </td>
                                <td class="nums px-5 py-3 text-right text-ink-soft">{{ $row['owed']->format() }}</td>
                                <td class="nums px-5 py-3 text-right text-paid">{{ $row['paid']->format() }}</td>
                                <td class="nums px-5 py-3 text-right font-semibold {{ $row['due']->isPositive() ? 'text-overdue' : 'text-ink-muted' }}">
                                    {{ $row['due']->format() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="surface p-5">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">Details</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-ink-muted">Default rate for new projects</dt>
                    <dd class="nums font-semibold text-ink">
                        {{ \App\Support\Percent::withSign($partner->default_commission_percent) }}
                    </dd>
                </div>
                @foreach (['Email' => $partner->email, 'Phone' => $partner->phone] as $label => $value)
                    @if ($value)
                        <div>
                            <dt class="text-xs text-ink-muted">{{ $label }}</dt>
                            <dd class="text-ink">{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>

            @if ($partner->notes)
                <p class="mt-4 whitespace-pre-line border-t border-line pt-4 text-sm leading-relaxed text-ink-soft">
                    {{ $partner->notes }}
                </p>
            @endif
        </div>

        <x-ui.invite-panel
            :action="route('admin.partners.invite', $partner)"
            :users="$partner->portalUsers"
            label="Partner portal"
            blurb="Lets them see the projects they brought, progress, shared files and what they have earned." />

        <div class="surface overflow-hidden">
            <div class="border-b border-line px-5 py-3.5">
                <h2 class="font-semibold text-ink">Clients introduced</h2>
            </div>

            @forelse ($clients as $client)
                <div class="flex items-center justify-between border-b border-line px-5 py-3 last:border-0">
                    <a href="{{ route('admin.clients.show', $client) }}"
                       class="text-sm font-medium text-ink hover:text-brand-text">{{ $client->name }}</a>
                    <span class="nums text-xs text-ink-muted">{{ $client->projects_count }} projects</span>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-ink-muted">
                    No clients linked to {{ $partner->name }} yet.
                </p>
            @endforelse
        </div>
    </section>
</x-layouts.admin>
