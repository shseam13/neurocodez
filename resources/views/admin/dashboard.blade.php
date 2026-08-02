<x-layouts.admin title="Dashboard" heading="Dashboard">
    {{-- KPI tiles: glass, because they are few and large. --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Receivable', $totals['receivable'], 'ink', 'agreed on open projects'],
            ['Collected', $totals['collected'], 'paid', 'received so far'],
            ['Outstanding', $totals['outstanding'], 'due-soon', 'still owed to you'],
            ['Commissions payable', $commissionsPayable, 'overdue', 'owed to partners'],
        ] as [$label, $amount, $tone, $meta])
            <div class="glass p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</p>
                <p class="nums mt-2 text-2xl font-bold text-{{ $tone }}">
                    <span class="text-sm font-medium text-ink-soft">{{ $amount->currency }}</span>
                    {{ $amount->format(false) }}
                </p>
                <p class="mt-1 text-xs text-ink-muted">{{ $meta }}</p>
            </div>
        @endforeach
    </section>

    <section class="mt-6 grid gap-5 lg:grid-cols-2">
        {{-- Solid surface: this is data. --}}
        <div class="surface overflow-hidden">
            <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                <h2 class="font-semibold text-ink">Overdue</h2>
                @if ($totals['overdue_count'] > 0)
                    <span class="badge badge-overdue">{{ $totals['overdue_count'] }} past deadline</span>
                @endif
            </div>

            @forelse ($overdue as $project)
                <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink">{{ $project->title }}</p>
                        <p class="truncate text-xs text-ink-muted">{{ $project->client->name }}</p>
                    </div>
                    <span class="nums shrink-0 text-xs font-medium text-overdue">
                        {{ $project->deadline->diffForHumans(short: true) }}
                    </span>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-ink-muted">Nothing overdue. Good.</p>
            @endforelse
        </div>

        <div class="surface overflow-hidden">
            <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                <h2 class="font-semibold text-ink">Active projects</h2>
                @if ($newLeads > 0)
                    <span class="badge badge-info">{{ $newLeads }} new {{ Str::plural('lead', $newLeads) }}</span>
                @endif
            </div>

            @forelse ($recent as $project)
                <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink">{{ $project->title }}</p>
                        <p class="truncate text-xs text-ink-muted">{{ $project->client->name }}</p>
                    </div>
                    @if ($project->currentStage)
                        <span class="shrink-0 rounded-md bg-surface-alt px-2 py-0.5 text-xs text-ink-soft">
                            {{ $project->currentStage->name }}
                        </span>
                    @endif
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-ink-muted">No active projects yet.</p>
            @endforelse
        </div>
    </section>
</x-layouts.admin>
