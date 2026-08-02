<x-layouts.admin :title="$client->name" :heading="$client->name">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.clients.index') }}" class="text-sm text-ink-muted hover:text-ink">&larr; All clients</a>

        @can('update', $client)
            <a href="{{ route('admin.clients.edit', $client) }}"
               class="ml-auto glass-flat rounded-xl px-4 py-2 text-sm font-medium text-ink">Edit</a>
        @endcan
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_320px]">
        <div class="surface overflow-hidden">
            <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                <h2 class="font-semibold text-ink">Projects</h2>
                <span class="text-xs text-ink-muted">{{ $client->projects->count() }} total</span>
            </div>

            @forelse ($client->projects as $project)
                @php($f = $finance[$project->id])
                <div class="border-b border-line px-5 py-4 last:border-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-ink">{{ $project->title }}</p>
                            <p class="mt-0.5 text-xs text-ink-muted">
                                {{ $project->status->label() }}
                                @if ($project->deadline)
                                    &middot; due {{ $project->deadline->format('j M Y') }}
                                @endif
                            </p>
                        </div>

                        <span class="badge badge-{{ $f['fully_paid'] ? 'paid' : ($f['overdue'] ? 'overdue' : 'due') }}">
                            {{ $f['fully_paid'] ? 'Paid' : $f['due']->formatWithCurrency() . ' due' }}
                        </span>
                    </div>

                    @if ($f['agreed']->isPositive())
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-alt">
                            <div class="h-full rounded-full bg-paid" style="width: {{ $f['paid_percent'] }}%"></div>
                        </div>
                        <p class="nums mt-1.5 text-xs text-ink-muted">
                            {{ $f['paid']->format() }} of {{ $f['agreed']->format() }} collected
                            ({{ $f['paid_percent'] }}%)
                        </p>
                    @endif
                </div>
            @empty
                <p class="px-5 py-10 text-center text-sm text-ink-muted">No projects for this client yet.</p>
            @endforelse
        </div>

        <aside class="space-y-5">
            <div class="surface p-5">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">Contact</h2>
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        'Company' => $client->company,
                        'Email' => $client->email,
                        'Phone' => $client->phone,
                        'Address' => $client->address,
                    ] as $label => $value)
                        @if ($value)
                            <div>
                                <dt class="text-xs text-ink-muted">{{ $label }}</dt>
                                <dd class="text-ink">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach

                    <div>
                        <dt class="text-xs text-ink-muted">Referred by</dt>
                        <dd class="text-ink">
                            @if ($client->partner)
                                <a href="{{ route('admin.partners.show', $client->partner) }}"
                                   class="text-brand-text hover:underline">{{ $client->partner->name }}</a>
                            @else
                                Direct client
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <x-ui.invite-panel
                :action="route('admin.clients.invite', $client)"
                :users="$client->portalUsers"
                label="Client portal"
                blurb="Lets them track progress, download shared files and see invoices." />

            @if ($client->notes)
                <div class="surface p-5">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                        Internal notes
                    </h2>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-ink-soft">{{ $client->notes }}</p>
                    <p class="mt-3 text-xs text-ink-muted">Never shown in the client portal.</p>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.admin>
