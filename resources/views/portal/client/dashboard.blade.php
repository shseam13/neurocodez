{{--
    Client portal.

    Nothing here may reference commission, partner identity, or internal stage
    names. The timeline arrives pre-filtered by StageService for the Client
    audience, so internal stages have already collapsed into the previous
    visible one.
--}}
<x-layouts.admin title="My projects" heading="My projects">
    @forelse ($projects as $row)
        @php($project = $row['project'])
        @php($finance = $row['finance'])

        <article class="surface mb-5 overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line px-5 py-4">
                <div class="min-w-0">
                    <h2 class="font-semibold text-ink">{{ $project->title }}</h2>
                    @if ($project->deadline)
                        <p class="mt-1 text-xs text-ink-muted">
                            Due {{ $project->deadline->format('j M Y') }}
                        </p>
                    @endif
                </div>

                <span class="badge badge-{{ $finance['fully_paid'] ? 'paid' : ($finance['overdue'] ? 'overdue' : 'due') }}">
                    {{ $finance['fully_paid'] ? 'Fully paid' : ($finance['overdue'] ? 'Overdue' : 'Payment due') }}
                </span>
            </div>

            @if ($row['timeline']->isNotEmpty())
                <div class="border-b border-line px-5 py-5">
                    <ol class="flex flex-wrap gap-2">
                        @foreach ($row['timeline'] as $step)
                            <li @class([
                                'rounded-lg px-3 py-1.5 text-xs font-medium',
                                'bg-paid/15 text-paid' => $step['state'] === 'done',
                                'bg-brand text-white' => $step['state'] === 'current',
                                'bg-surface-alt text-ink-muted' => $step['state'] === 'upcoming',
                            ])>
                                {{ $step['label'] }}
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if ($project->files->isNotEmpty())
                <div class="border-b border-line px-5 py-4">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                        Files for you
                    </h3>
                    <ul class="space-y-2">
                        @foreach ($project->files as $file)
                            <li class="flex flex-wrap items-center justify-between gap-3">
                                <span class="min-w-0 truncate text-sm text-ink">{{ $file->original_name }}</span>
                                <span class="flex shrink-0 items-center gap-3">
                                    <span class="nums text-xs text-ink-muted">{{ $file->humanSize() }}</span>
                                    <a href="{{ route('files.download', $file) }}"
                                       class="text-xs font-medium text-brand-text hover:underline">Download</a>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <dl class="grid grid-cols-3 divide-x divide-line">
                @foreach ([
                    ['Agreed', $finance['contract'], 'ink'],
                    ['Paid', $finance['paid'], 'paid'],
                    ['Balance due', $finance['due'], $finance['fully_paid'] ? 'ink-muted' : 'due-soon'],
                ] as [$label, $amount, $tone])
                    <div class="px-5 py-4">
                        <dt class="text-xs uppercase tracking-wider text-ink-muted">{{ $label }}</dt>
                        <dd class="nums mt-1 font-semibold text-{{ $tone }}">
                            <span class="text-xs font-normal text-ink-muted">{{ $amount->currency }}</span>
                            {{ $amount->format() }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </article>
    @empty
        <div class="surface p-10 text-center">
            <x-brand.mark :size="42" class="mx-auto text-brand opacity-40" />
            <h2 class="mt-5 font-semibold text-ink">No projects yet</h2>
            <p class="mt-2 text-sm text-ink-soft">
                Once work begins, you'll see progress, files and invoices here.
            </p>
        </div>
    @endforelse
</x-layouts.admin>
