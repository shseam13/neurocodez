@php($editing = $set->exists)

<x-layouts.admin :title="$editing ? $set->name : 'New stage set'"
                 :heading="$editing ? 'Edit '.$set->name : 'New stage set'">

    <a href="{{ route('admin.stage-sets.index') }}" class="mb-5 inline-block text-sm text-ink-muted hover:text-ink">
        &larr; All stage sets
    </a>

    <div class="mx-auto max-w-3xl space-y-5">
        <form method="POST"
              action="{{ $editing ? route('admin.stage-sets.update', $set) : route('admin.stage-sets.store') }}"
              class="surface p-6">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field name="name" label="Name" required :value="old('name', $set->name)"
                            placeholder="e.g. Web Development" />
                <x-ui.field name="description" label="Description" :value="old('description', $set->description)" />
            </div>

            <label class="mt-5 flex items-center gap-2.5 text-sm text-ink">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $set->exists ? $set->is_active : true))
                       class="h-4 w-4 rounded border-line text-brand focus:ring-brand/40">
                Available for new projects
            </label>

            <button type="submit"
                    class="mt-5 rounded-xl bg-brand px-6 py-2.5 font-semibold text-white transition hover:bg-brand-hover">
                {{ $editing ? 'Save' : 'Create and add stages' }}
            </button>
        </form>

        @if ($editing)
            @error('stage')
                <div class="rounded-xl border border-overdue/40 bg-overdue/10 p-4 text-sm font-medium text-overdue">
                    {{ $message }}
                </div>
            @enderror

            <div class="surface overflow-hidden">
                <div class="border-b border-line px-5 py-3.5">
                    <h2 class="font-semibold text-ink">Stages</h2>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        Order is the order work moves through. Hidden stages collapse into the
                        previous visible one for that audience — the client sees steady progress,
                        never your internal steps.
                    </p>
                </div>

                {{-- Each action is its own form.

                     Sharing one form and overriding with formaction does not
                     work here: Laravel routes on the hidden _method field, so a
                     PUT on the wrapper would be sent with the move and delete
                     buttons too. Forms cannot nest, so they sit as siblings and
                     buttons bind to them by id. --}}
                @forelse ($set->stages as $stage)
                    <div class="border-b border-line px-5 py-4 last:border-0">
                        <form id="stage-move-up-{{ $stage->id }}" method="POST"
                              action="{{ route('admin.stage-sets.stages.move', [$set, $stage]) }}" class="hidden">
                            @csrf <input type="hidden" name="direction" value="up">
                        </form>
                        <form id="stage-move-down-{{ $stage->id }}" method="POST"
                              action="{{ route('admin.stage-sets.stages.move', [$set, $stage]) }}" class="hidden">
                            @csrf <input type="hidden" name="direction" value="down">
                        </form>
                        <form id="stage-delete-{{ $stage->id }}" method="POST"
                              action="{{ route('admin.stage-sets.stages.destroy', [$set, $stage]) }}" class="hidden">
                            @csrf @method('DELETE')
                        </form>

                        <form method="POST" action="{{ route('admin.stage-sets.stages.update', [$set, $stage]) }}">
                            @csrf @method('PUT')

                            <div class="flex flex-wrap items-center gap-3">
                                <span class="nums w-6 shrink-0 text-xs text-ink-muted">{{ $loop->iteration }}</span>

                                <input type="text" name="name" value="{{ $stage->name }}" required
                                       class="min-w-40 flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm font-medium text-ink focus:border-brand focus:outline-none">

                                <input type="text" name="client_label" value="{{ $stage->client_label }}"
                                       placeholder="Client-facing name (optional)"
                                       class="min-w-40 flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">

                                <div class="ml-auto flex items-center gap-1">
                                    <button type="submit" form="stage-move-up-{{ $stage->id }}" @disabled($loop->first)
                                            aria-label="Move {{ $stage->name }} earlier"
                                            class="rounded-md px-2 py-1 text-sm text-ink-muted hover:bg-surface-alt hover:text-ink disabled:opacity-30">&uarr;</button>
                                    <button type="submit" form="stage-move-down-{{ $stage->id }}" @disabled($loop->last)
                                            aria-label="Move {{ $stage->name }} later"
                                            class="rounded-md px-2 py-1 text-sm text-ink-muted hover:bg-surface-alt hover:text-ink disabled:opacity-30">&darr;</button>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 pl-9 text-xs">
                                @foreach ([
                                    'visible_to_client' => 'Client sees this',
                                    'visible_to_partner' => 'Partner sees this',
                                    'is_terminal' => 'Ends the project',
                                ] as $field => $label)
                                    <label class="flex items-center gap-1.5 text-ink-soft">
                                        <input type="checkbox" name="{{ $field }}" value="1" @checked($stage->$field)
                                               class="h-3.5 w-3.5 rounded border-line text-brand focus:ring-brand/40">
                                        {{ $label }}
                                    </label>
                                @endforeach

                                <button type="submit" class="ml-auto font-medium text-brand-text hover:underline">Save</button>

                                <button type="submit" form="stage-delete-{{ $stage->id }}"
                                        onclick="return confirm('Remove {{ addslashes($stage->name) }}? Past project history keeps it.')"
                                        class="text-ink-muted hover:text-overdue">Remove</button>
                            </div>
                        </form>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-ink-muted">
                        No stages yet. Add the first one below.
                    </p>
                @endforelse

                <form method="POST" action="{{ route('admin.stage-sets.stages.store', $set) }}"
                      class="border-t border-line bg-surface-alt px-5 py-4">
                    @csrf
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Add a stage</p>

                    <div class="flex flex-wrap gap-3">
                        <input type="text" name="name" placeholder="Stage name, e.g. Code review" required
                               class="min-w-40 flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                        <input type="text" name="client_label" placeholder="Client-facing name (optional)"
                               class="min-w-40 flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
                        <label class="flex items-center gap-1.5 text-ink-soft">
                            <input type="checkbox" name="visible_to_client" value="1" checked
                                   class="h-3.5 w-3.5 rounded border-line text-brand focus:ring-brand/40">
                            Client sees this
                        </label>
                        <label class="flex items-center gap-1.5 text-ink-soft">
                            <input type="checkbox" name="visible_to_partner" value="1"
                                   class="h-3.5 w-3.5 rounded border-line text-brand focus:ring-brand/40">
                            Partner sees this
                        </label>
                        <label class="flex items-center gap-1.5 text-ink-soft">
                            <input type="checkbox" name="is_terminal" value="1"
                                   class="h-3.5 w-3.5 rounded border-line text-brand focus:ring-brand/40">
                            Ends the project
                        </label>

                        <button type="submit"
                                class="ml-auto rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                            Add stage
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-layouts.admin>
