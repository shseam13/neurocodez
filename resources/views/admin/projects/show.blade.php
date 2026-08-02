<x-layouts.admin :title="$project->title" :heading="$project->title">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.projects.index') }}" class="text-sm text-ink-muted hover:text-ink">&larr; All projects</a>

        <span class="badge badge-{{ $project->status->tone() }}">{{ $project->status->label() }}</span>

        @if ($project->is_retainer)
            <span class="badge badge-info">Retainer</span>
        @endif

        @can('update', $project)
            <a href="{{ route('admin.projects.edit', $project) }}"
               class="ml-auto glass-flat rounded-xl px-4 py-2 text-sm font-medium text-ink">Edit</a>
        @endcan
    </div>

    <p class="mb-5 text-sm text-ink-soft">
        <a href="{{ route('admin.clients.show', $project->client) }}" class="text-brand-text hover:underline">
            {{ $project->client->name }}
        </a>
        @if ($project->parent)
            &middot; follow-up to
            <a href="{{ route('admin.projects.show', $project->parent) }}" class="text-brand-text hover:underline">
                {{ $project->parent->title }}
            </a>
        @endif
        @if ($project->deadline)
            &middot; due {{ $project->deadline->format('j M Y') }}
        @endif
    </p>

    {{-- Money --------------------------------------------------------------- --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Original scope', $finance['agreed'], 'ink', 'as first agreed'],
            ['Extra work', $finance['extras'], 'info', 'approved additions'],
            ['Collected', $finance['paid'], 'paid', $finance['paid_percent'].'% of contract'],
            ['Outstanding', $finance['due'], $finance['fully_paid'] ? 'ink-muted' : 'due-soon', 'still owed'],
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

    @if ($finance['extras']->isPositive())
        <p class="nums mt-3 text-sm text-ink-soft">
            Contract value <strong class="text-ink">{{ $finance['contract']->formatWithCurrency() }}</strong>
            — {{ $finance['agreed']->format() }} original + {{ $finance['extras']->format() }} extra.
        </p>
    @endif

    <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_340px]">
        <div class="space-y-5">
            {{-- Stages ---------------------------------------------------- --}}
            @if ($timeline->isNotEmpty())
                <section class="surface overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                        <h2 class="font-semibold text-ink">Progress</h2>

                        @can('update', $project)
                            @if ($nextStage)
                                <form method="POST" action="{{ route('admin.projects.stage.advance', $project) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-hover">
                                        Move to {{ $nextStage->name }} &rarr;
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </div>

                    <div class="px-5 py-5">
                        <ol class="flex flex-wrap gap-2">
                            @foreach ($timeline as $step)
                                <li @class([
                                    'rounded-lg px-3 py-1.5 text-xs font-medium',
                                    'bg-paid/15 text-paid' => $step['state'] === 'done',
                                    'bg-brand text-white' => $step['state'] === 'current',
                                    'bg-surface-alt text-ink-muted' => $step['state'] === 'upcoming',
                                ])>{{ $step['label'] }}</li>
                            @endforeach
                        </ol>

                        {{-- What the client actually sees. Internal stages
                             collapse, so this is worth showing before anyone
                             wonders why the portal looks different. --}}
                        @if ($clientTimeline->pluck('label')->all() !== $timeline->pluck('label')->all())
                            <div class="mt-5 border-t border-line pt-4">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                                    What the client sees
                                </p>
                                <ol class="flex flex-wrap gap-2">
                                    @foreach ($clientTimeline as $step)
                                        <li @class([
                                            'rounded-lg px-2.5 py-1 text-xs',
                                            'bg-paid/10 text-paid' => $step['state'] === 'done',
                                            'bg-brand/20 text-brand-text' => $step['state'] === 'current',
                                            'bg-surface-alt text-ink-muted' => $step['state'] === 'upcoming',
                                        ])>{{ $step['label'] }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>

                    @can('update', $project)
                        <form method="POST" action="{{ route('admin.projects.stage.move', $project) }}"
                              class="flex flex-wrap gap-2 border-t border-line px-5 py-3">
                            @csrf
                            <select name="stage_id"
                                    class="flex-1 rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-brand focus:outline-none">
                                @foreach ($project->stageSet->stages as $stage)
                                    <option value="{{ $stage->id }}" @selected($project->current_stage_id === $stage->id)>
                                        {{ $stage->name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="text" name="note" placeholder="Note (optional)"
                                   class="flex-1 rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                            <button type="submit" class="glass-flat rounded-lg px-3 py-1.5 text-sm font-medium text-ink">
                                Set stage
                            </button>
                        </form>
                    @endcan
                </section>
            @endif

            {{-- Money in / money out ------------------------------------- --}}
            @include('admin.projects._money')

            {{-- Files ------------------------------------------------------ --}}
            @include('admin.projects._files')

            {{-- Charges --------------------------------------------------- --}}
            <section class="surface overflow-hidden">
                <div class="border-b border-line px-5 py-3.5">
                    <h2 class="font-semibold text-ink">Extra work &amp; revisions</h2>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        Added on top of the original scope — the agreed amount above never changes.
                    </p>
                </div>

                @forelse ($project->charges as $charge)
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-ink">
                                {{ $charge->title }}
                                <span class="badge badge-{{ $charge->status->tone() }} ml-1">{{ $charge->status->label() }}</span>
                                @unless ($charge->commission_applies)
                                    <span class="badge badge-info ml-1">No commission</span>
                                @endunless
                            </p>
                            <p class="mt-0.5 text-xs text-ink-muted">
                                {{ $charge->kind->label() }} &middot; {{ $charge->occurred_at->format('j M Y') }}
                                @if ($charge->createdBy) &middot; {{ $charge->createdBy->name }} @endif
                            </p>
                            @if ($charge->description)
                                <p class="mt-1 text-xs text-ink-soft">{{ $charge->description }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <span class="nums text-sm font-semibold text-ink">{{ $charge->amount->format() }}</span>

                            @can('manageCharges', $project)
                                @if ($charge->status === \App\Enums\ChargeStatus::Quoted)
                                    <form method="POST" action="{{ route('admin.projects.charges.approve', [$project, $charge]) }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-paid hover:underline">Approve</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.projects.charges.destroy', [$project, $charge]) }}"
                                      onsubmit="return confirm('Remove this charge?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-ink-muted hover:text-overdue">Remove</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-ink-muted">
                        No extra work recorded. Everything so far is within the original scope.
                    </p>
                @endforelse

                @can('manageCharges', $project)
                    <form method="POST" action="{{ route('admin.projects.charges.store', $project) }}"
                          class="border-t border-line bg-surface-alt px-5 py-4">
                        @csrf
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Add a charge</p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <input type="text" name="title" placeholder="What was it? e.g. Three extra pages" required
                                   value="{{ old('title') }}"
                                   class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">

                            <input type="number" name="amount" step="0.01" min="0" placeholder="Amount" required
                                   value="{{ old('amount') }}"
                                   class="nums rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">

                            <select name="kind" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">
                                @foreach ([\App\Enums\ChargeKind::Extra, \App\Enums\ChargeKind::Revision, \App\Enums\ChargeKind::Maintenance] as $kind)
                                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                @endforeach
                            </select>

                            <input type="date" name="occurred_at" required value="{{ old('occurred_at', now()->toDateString()) }}"
                                   class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-4">
                            <label class="flex items-center gap-2 text-sm text-ink-soft">
                                <input type="radio" name="status" value="quoted" checked
                                       class="h-4 w-4 border-line text-brand focus:ring-brand/40">
                                Quote it first
                            </label>
                            <label class="flex items-center gap-2 text-sm text-ink-soft">
                                <input type="radio" name="status" value="approved"
                                       class="h-4 w-4 border-line text-brand focus:ring-brand/40">
                                Already agreed
                            </label>

                            @if ($project->earnsCommission())
                                <label class="flex items-center gap-2 text-sm text-ink-soft">
                                    <input type="checkbox" name="commission_applies" value="1" checked
                                           class="h-4 w-4 rounded border-line text-brand focus:ring-brand/40">
                                    Earns {{ $project->partner->name }} commission
                                </label>
                            @endif

                            <button type="submit"
                                    class="ml-auto rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                                Add charge
                            </button>
                        </div>
                    </form>
                @endcan
            </section>
        </div>

        {{-- Sidebar ------------------------------------------------------- --}}
        <aside class="space-y-5">
            @if ($commission)
                <div class="surface p-5">
                    <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-ink-muted">Partner commission</h2>

                    @if ($commission['applies'])
                        <p class="text-sm text-ink">
                            <a href="{{ route('admin.partners.show', $project->partner) }}" class="text-brand-text hover:underline">
                                {{ $project->partner->name }}
                            </a>
                            at {{ \App\Support\Percent::withSign($commission['percent']) }}
                        </p>
                        <p class="mt-0.5 text-xs text-ink-muted">{{ $commission['basis']->label() }}</p>

                        <dl class="nums mt-4 space-y-2 text-sm">
                            @foreach ([
                                'Base' => $commission['base'],
                                'Earned' => $commission['owed'],
                                'Paid out' => $commission['paid'],
                                'Still owed' => $commission['due'],
                            ] as $label => $amount)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-ink-muted">{{ $label }}</dt>
                                    <dd class="font-medium text-ink">{{ $amount->format() }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @if ($commission['excluded']->isPositive())
                            <p class="mt-3 border-t border-line pt-3 text-xs text-ink-muted">
                                {{ $commission['excluded']->formatWithCurrency() }} of extra work is excluded
                                from commission.
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-ink-soft">No partner on this project.</p>
                    @endif
                </div>
            @endif

            @if ($project->is_retainer)
                <div class="surface p-5">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Retainer</h2>
                    <p class="nums text-lg font-bold text-ink">
                        {{ $project->retainer_amount?->formatWithCurrency() }}<span class="text-sm font-normal text-ink-muted">/month</span>
                    </p>
                    <p class="mt-1 text-xs text-ink-muted">
                        Billed on day {{ $project->retainer_day }}
                        @if ($project->retainer_ends_on)
                            &middot; ends {{ $project->retainer_ends_on->format('j M Y') }}
                        @endif
                    </p>
                    <p class="mt-3 text-xs {{ $project->retainerIsActive() ? 'text-paid' : 'text-ink-muted' }}">
                        {{ $project->retainerIsActive() ? 'Active — generating monthly' : 'Not currently billing' }}
                    </p>
                </div>
            @endif

            @if ($project->followUps->isNotEmpty())
                <div class="surface overflow-hidden">
                    <div class="border-b border-line px-5 py-3">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Follow-up work</h2>
                    </div>
                    @foreach ($project->followUps as $child)
                        <a href="{{ route('admin.projects.show', $child) }}"
                           class="block border-b border-line px-5 py-3 text-sm text-ink last:border-0 hover:text-brand-text">
                            {{ $child->title }}
                            <span class="block text-xs text-ink-muted">{{ $child->status->label() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="surface overflow-hidden">
                <div class="border-b border-line px-5 py-3">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Invoices</h2>
                </div>

                @forelse ($project->invoices as $invoice)
                    <a href="{{ route('admin.invoices.edit', $invoice) }}"
                       class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 text-sm last:border-0 hover:text-brand-text">
                        <span class="nums text-ink">{{ $invoice->number }}</span>
                        <span class="badge badge-{{ $invoice->isOverdue() ? 'overdue' : $invoice->status->tone() }}">
                            {{ $invoice->isOverdue() ? 'Overdue' : $invoice->status->label() }}
                        </span>
                    </a>
                @empty
                    <p class="px-5 py-4 text-sm text-ink-muted">No invoices raised yet.</p>
                @endforelse

                @can('create', App\Models\Invoice::class)
                    <form method="POST" action="{{ route('admin.projects.invoices.store', $project) }}"
                          class="border-t border-line bg-surface-alt px-5 py-3">
                        @csrf
                        <button type="submit" class="text-sm font-semibold text-brand-text hover:underline">
                            + Raise an invoice
                        </button>
                        <p class="mt-1 text-xs text-ink-muted">
                            Line items come from the agreed scope plus each approved charge.
                        </p>
                    </form>
                @endcan
            </div>

            @can('create', App\Models\Project::class)
                <a href="{{ route('admin.projects.create', ['parent' => $project->id]) }}"
                   class="glass-flat block rounded-xl px-4 py-3 text-center text-sm font-medium text-ink">
                    + Start follow-up project
                </a>
            @endcan
        </aside>
    </div>
</x-layouts.admin>
