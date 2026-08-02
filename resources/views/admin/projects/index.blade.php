<x-layouts.admin title="Projects" heading="Projects">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.projects.index') }}" class="flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search project or client"
                   class="w-64 max-w-full rounded-xl border border-line bg-surface px-4 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40">

            <select name="status" onchange="this.form.submit()"
                    class="rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">
                <option value="">Open projects</option>
                @foreach ([
                    'overdue' => 'Overdue',
                    'active' => 'Active',
                    'on_hold' => 'On hold',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <button type="submit" class="glass-flat rounded-xl px-4 py-2 text-sm font-medium text-ink">Filter</button>
        </form>

        @can('create', App\Models\Project::class)
            <a href="{{ route('admin.projects.create') }}"
               class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                New project
            </a>
        @endcan
    </div>

    @error('project')
        <div class="mb-5 rounded-xl border border-overdue/40 bg-overdue/10 p-4 text-sm font-medium text-overdue">
            {{ $message }}
        </div>
    @enderror

    <div class="surface overflow-hidden">
        @if ($projects->isEmpty())
            <div class="p-12 text-center">
                <x-brand.mark :size="40" class="mx-auto text-brand opacity-40" />
                <h2 class="mt-5 font-semibold text-ink">No projects here</h2>
                <p class="mt-2 text-sm text-ink-soft">
                    {{ $search !== '' || $status !== '' ? 'Try a different filter.' : 'Create your first project to start tracking money and progress.' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Project</th>
                            <th class="px-5 py-3 font-semibold">Stage</th>
                            <th class="px-5 py-3 text-right font-semibold">Value</th>
                            <th class="px-5 py-3 text-right font-semibold">Due</th>
                            <th class="px-5 py-3 font-semibold">Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            @php($f = $finance[$project->id])
                            <tr class="border-b border-line last:border-0">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.projects.show', $project) }}"
                                       class="font-medium text-ink hover:text-brand-text">{{ $project->title }}</a>
                                    <p class="text-xs text-ink-muted">
                                        {{ $project->client->name }}
                                        @if ($project->is_retainer)
                                            <span class="badge badge-info ml-1">Retainer</span>
                                        @endif
                                        @if ($project->parent_id)
                                            <span class="badge badge-info ml-1">Follow-up</span>
                                        @endif
                                    </p>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($project->currentStage)
                                        <span class="rounded-md bg-surface-alt px-2 py-0.5 text-xs text-ink-soft">
                                            {{ $project->currentStage->name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td class="nums px-5 py-3 text-right text-ink-soft">
                                    {{ $f['contract']->format(false) }}
                                    @if ($f['extras']->isPositive())
                                        <span class="block text-xs text-ink-muted">
                                            incl. {{ $f['extras']->format(false) }} extra
                                        </span>
                                    @endif
                                </td>
                                <td class="nums px-5 py-3 text-right font-semibold {{ $f['fully_paid'] ? 'text-ink-muted' : 'text-ink' }}">
                                    {{ $f['due']->format(false) }}
                                </td>
                                <td class="px-5 py-3">
                                    @if ($f['overdue'])
                                        <span class="badge badge-overdue">
                                            {{ $project->deadline->diffForHumans(short: true) }}
                                        </span>
                                    @elseif ($project->deadline)
                                        <span class="text-xs text-ink-soft">{{ $project->deadline->format('j M Y') }}</span>
                                    @else
                                        <span class="text-xs text-ink-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($projects->hasPages())
        <div class="mt-5">{{ $projects->links() }}</div>
    @endif
</x-layouts.admin>
