{{--
    Partner portal.

    Shows only projects this partner introduced, and only their own commission
    position. No other partner's data, and no client contact details.
--}}
<x-layouts.admin title="My partner" heading="My partner">
    @if ($summary['total_owed'])
        <section class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['Earned', $summary['total_owed'], 'ink'],
                ['Paid to you', $summary['total_paid'], 'paid'],
                ['Outstanding', $summary['total_due'], 'due-soon'],
            ] as [$label, $amount, $tone])
                <div class="glass p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</p>
                    <p class="nums mt-2 text-2xl font-bold text-{{ $tone }}">
                        <span class="text-sm font-medium text-ink-soft">{{ $amount->currency }}</span>
                        {{ $amount->format() }}
                    </p>
                </div>
            @endforeach
        </section>
    @endif

    {{-- Progress and shared files, for partners acting as the point of contact.
         The timeline is filtered to the Partner audience, so internal stages
         never appear here. --}}
    @if ($projects->isNotEmpty())
        <section class="mt-6 space-y-4">
            <h2 class="font-semibold text-ink">Project progress</h2>

            @foreach ($projects as $row)
                @php($project = $row['project'])
                <article class="surface overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-ink">{{ $project->title }}</h3>
                            <p class="mt-0.5 text-xs text-ink-muted">
                                {{ $project->client->name }}
                                @if ($project->deadline)
                                    &middot; due {{ $project->deadline->format('j M Y') }}
                                @endif
                            </p>
                        </div>
                        <span class="badge badge-{{ $project->status->tone() }}">{{ $project->status->label() }}</span>
                    </div>

                    @if ($row['timeline']->isNotEmpty())
                        <div class="border-b border-line px-5 py-4">
                            <ol class="flex flex-wrap gap-2">
                                @foreach ($row['timeline'] as $step)
                                    <li @class([
                                        'rounded-lg px-3 py-1.5 text-xs font-medium',
                                        'bg-paid/15 text-paid' => $step['state'] === 'done',
                                        'bg-brand text-white' => $step['state'] === 'current',
                                        'bg-surface-alt text-ink-muted' => $step['state'] === 'upcoming',
                                    ])>{{ $step['label'] }}</li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    @if ($project->files->isNotEmpty())
                        <div class="px-5 py-4">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-muted">Files</h4>
                            <ul class="space-y-2">
                                @foreach ($project->files as $file)
                                    <li class="flex flex-wrap items-center justify-between gap-3">
                                        <span class="min-w-0 truncate text-sm text-ink">{{ $file->original_name }}</span>
                                        <a href="{{ route('files.download', $file) }}"
                                           class="shrink-0 text-xs font-medium text-brand-text hover:underline">Download</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    <section class="surface mt-6 overflow-hidden">
        <div class="border-b border-line px-5 py-3.5">
            <h2 class="font-semibold text-ink">Commission</h2>
        </div>

        @if (count($summary['projects']) === 0)
            <div class="p-10 text-center">
                <x-brand.mark :size="42" class="mx-auto text-brand opacity-40" />
                <h3 class="mt-5 font-semibold text-ink">No partner yet</h3>
                <p class="mt-2 text-sm text-ink-soft">
                    When a client you introduced starts a project, it will appear here.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Project</th>
                            <th class="px-5 py-3 font-semibold">Client</th>
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
                                <td class="nums px-5 py-3 text-right text-ink-soft">{{ \App\Support\Percent::withSign($row['percent']) }}</td>
                                <td class="nums px-5 py-3 text-right text-ink-soft">{{ $row['owed']->format() }}</td>
                                <td class="nums px-5 py-3 text-right text-paid">{{ $row['paid']->format() }}</td>
                                <td class="nums px-5 py-3 text-right font-semibold text-ink">{{ $row['due']->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="border-t border-line px-5 py-3 text-xs text-ink-muted">
                Commission accrues as each client pays, so "earned" grows over the life of a project.
            </p>
        @endif
    </section>
</x-layouts.admin>
