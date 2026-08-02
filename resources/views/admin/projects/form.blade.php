@php($editing = $project->exists)

<x-layouts.admin :title="$editing ? 'Edit '.$project->title : 'New project'"
                 :heading="$editing ? 'Edit '.$project->title : 'New project'">

    <form method="POST"
          action="{{ $editing ? route('admin.projects.update', $project) : route('admin.projects.store') }}"
          class="mx-auto max-w-3xl space-y-5">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Basics ------------------------------------------------------- --}}
        <div class="surface p-6">
            <h2 class="mb-5 font-semibold text-ink">The work</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="client_id" class="mb-1.5 block text-sm font-medium text-ink">
                        Client <span class="text-overdue">*</span>
                    </label>
                    <select id="client_id" name="client_id" required
                            class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40">
                        <option value="">Choose a client…</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id', $project->client_id) == $client->id)>
                                {{ $client->name }}@if ($client->company) — {{ $client->company }}@endif
                            </option>
                        @endforeach
                    </select>
                    @error('client_id')<p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
                </div>

                <x-ui.field name="title" label="Project title" required :value="old('title', $project->title)" />
            </div>

            <div class="mt-5">
                <x-ui.field name="description" label="Description" type="textarea"
                            :value="old('description', $project->description)" />
            </div>

            @if ($parents->isNotEmpty())
                <div class="mt-5">
                    <label for="parent_id" class="mb-1.5 block text-sm font-medium text-ink">Follow-up to</label>
                    <select id="parent_id" name="parent_id"
                            class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none">
                        <option value="">Not a follow-up</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id', $project->parent_id) == $parent->id)>
                                {{ $parent->title }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-ink-muted">
                        Link maintenance or a later phase back to the original build. It keeps its own
                        stages, invoices and commission rate.
                    </p>
                </div>
            @endif
        </div>

        {{-- Money -------------------------------------------------------- --}}
        <div class="surface p-6">
            <h2 class="mb-5 font-semibold text-ink">Money</h2>

            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="agreed_amount" class="mb-1.5 block text-sm font-medium text-ink">
                        Agreed amount <span class="text-overdue">*</span>
                    </label>
                    <input id="agreed_amount" name="agreed_amount" type="number" step="0.01" min="0" required
                           value="{{ old('agreed_amount', $project->exists ? $project->agreed_amount->toMajor() : '') }}"
                           class="nums w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40">
                    <p class="mt-1.5 text-xs text-ink-muted">
                        The original scope. Extra work is added later as its own charge — this figure
                        stays as the record of what was first agreed.
                    </p>
                    @error('agreed_amount')<p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="currency" class="mb-1.5 block text-sm font-medium text-ink">Currency</label>
                    <select id="currency" name="currency"
                            class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none">
                        @foreach (['BDT', 'USD', 'EUR', 'GBP'] as $code)
                            <option value="{{ $code }}" @selected(old('currency', $project->currency ?? 'BDT') === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="partner_id" class="mb-1.5 block text-sm font-medium text-ink">Referred by</label>
                    <select id="partner_id" name="partner_id"
                            class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none">
                        <option value="">Nobody — no commission</option>
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}" @selected(old('partner_id', $project->partner_id) == $partner->id)>
                                {{ $partner->name }} ({{ \App\Support\Percent::withSign($partner->default_commission_percent) }} default)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="commission_percent" class="mb-1.5 block text-sm font-medium text-ink">
                        Commission rate for this project
                    </label>
                    <div class="relative">
                        <input id="commission_percent" name="commission_percent" type="number" step="0.01" min="0" max="100"
                               value="{{ old('commission_percent', $project->exists ? $project->commission_percent : '') }}"
                               placeholder="Use the partner's default"
                               class="nums w-full rounded-xl border border-line bg-surface px-4 py-2.5 pr-10 text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                        <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-sm text-ink-muted">%</span>
                    </div>
                    <p class="mt-1.5 text-xs text-ink-muted">
                        Locked to this project once set. Changing the partner's default later
                        will not affect it.
                    </p>
                    @error('commission_percent')<p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-5">
                <span class="mb-1.5 block text-sm font-medium text-ink">Who pays me</span>
                <div class="space-y-2">
                    @foreach (\App\Enums\BillingTarget::cases() as $target)
                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-line p-3 transition hover:border-brand/40">
                            <input type="radio" name="billed_to" value="{{ $target->value }}"
                                   @checked(old('billed_to', $project->billed_to?->value ?? 'client') === $target->value)
                                   class="mt-0.5 h-4 w-4 border-line text-brand focus:ring-brand/40">
                            <span>
                                <span class="block text-sm font-medium text-ink">{{ $target->label() }}</span>
                                <span class="block text-xs text-ink-soft">{{ $target->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="mt-5">
                <span class="mb-1.5 block text-sm font-medium text-ink">Commission is earned</span>
                <div class="space-y-2">
                    @foreach (\App\Enums\CommissionBasis::cases() as $basis)
                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-line p-3 transition hover:border-brand/40">
                            <input type="radio" name="commission_basis" value="{{ $basis->value }}"
                                   @checked(old('commission_basis', $project->commission_basis?->value ?? 'collected') === $basis->value)
                                   class="mt-0.5 h-4 w-4 border-line text-brand focus:ring-brand/40">
                            <span>
                                <span class="block text-sm font-medium text-ink">{{ $basis->label() }}</span>
                                <span class="block text-xs text-ink-soft">{{ $basis->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Retainer ------------------------------------------------------ --}}
        <div class="surface p-6">
            <label class="flex items-center gap-2.5">
                <input type="checkbox" name="is_retainer" value="1" id="is_retainer"
                       @checked(old('is_retainer', $project->is_retainer))
                       class="h-4 w-4 rounded border-line text-brand focus:ring-brand/40">
                <span class="font-semibold text-ink">This is a recurring maintenance retainer</span>
            </label>
            <p class="mt-1.5 text-xs text-ink-muted">
                A charge is created automatically each month, so a monthly fee cannot be forgotten.
            </p>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="retainer_amount" class="mb-1.5 block text-sm font-medium text-ink">Monthly amount</label>
                    <input id="retainer_amount" name="retainer_amount" type="number" step="0.01" min="0"
                           value="{{ old('retainer_amount', $project->retainer_amount?->toMajor()) }}"
                           class="nums w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none">
                    @error('retainer_amount')<p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="retainer_day" class="mb-1.5 block text-sm font-medium text-ink">Bill on day</label>
                    <input id="retainer_day" name="retainer_day" type="number" min="1" max="28"
                           value="{{ old('retainer_day', $project->retainer_day ?? 1) }}"
                           class="nums w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none">
                    <p class="mt-1.5 text-xs text-ink-muted">1–28, so every month has the day.</p>
                    @error('retainer_day')<p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
                </div>

                <x-ui.field name="retainer_starts_on" label="Starts" type="date"
                            :value="old('retainer_starts_on', $project->retainer_starts_on?->toDateString())" />
                <x-ui.field name="retainer_ends_on" label="Ends (optional)" type="date"
                            :value="old('retainer_ends_on', $project->retainer_ends_on?->toDateString())"
                            help="Billing stops on its own after this date." />
            </div>
        </div>

        {{-- Progress ------------------------------------------------------ --}}
        <div class="surface p-6">
            <h2 class="mb-5 font-semibold text-ink">Progress</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="stage_set_id" class="mb-1.5 block text-sm font-medium text-ink">Stage set</label>
                    <select id="stage_set_id" name="stage_set_id"
                            class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none">
                        <option value="">No stages</option>
                        @foreach ($stageSets as $set)
                            <option value="{{ $set->id }}" @selected(old('stage_set_id', $project->stage_set_id) == $set->id)>
                                {{ $set->name }} ({{ $set->stages->count() }} stages)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="mb-1.5 block text-sm font-medium text-ink">Status</label>
                    <select id="status" name="status"
                            class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none">
                        @foreach (\App\Enums\ProjectStatus::cases() as $case)
                            <option value="{{ $case->value }}" @selected(old('status', $project->status?->value ?? 'active') === $case->value)>
                                {{ $case->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <x-ui.field name="start_date" label="Start date" type="date"
                            :value="old('start_date', $project->start_date?->toDateString())" />
                <x-ui.field name="deadline" label="Deadline" type="date"
                            :value="old('deadline', $project->deadline?->toDateString())" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit"
                    class="rounded-xl bg-brand px-6 py-2.5 font-semibold text-white transition hover:bg-brand-hover">
                {{ $editing ? 'Save changes' : 'Create project' }}
            </button>
            <a href="{{ $editing ? route('admin.projects.show', $project) : route('admin.projects.index') }}"
               class="px-2 py-2.5 text-sm text-ink-muted hover:text-ink">Cancel</a>
        </div>
    </form>
</x-layouts.admin>
