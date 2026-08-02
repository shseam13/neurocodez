@php($editing = $partner->exists)

<x-layouts.admin :title="$editing ? 'Edit '.$partner->name : 'Add partner'"
                 :heading="$editing ? 'Edit '.$partner->name : 'Add partner'">

    <form method="POST"
          action="{{ $editing ? route('admin.partners.update', $partner) : route('admin.partners.store') }}"
          class="mx-auto max-w-2xl">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="surface p-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field name="name" label="Name" required :value="old('name', $partner->name)" />
                <x-ui.field name="email" label="Email" type="email" :value="old('email', $partner->email)" />
                <x-ui.field name="phone" label="Phone" :value="old('phone', $partner->phone)" />

                <div>
                    <label for="default_commission_percent" class="mb-1.5 block text-sm font-medium text-ink">
                        Default commission <span class="text-overdue" aria-hidden="true">*</span>
                    </label>
                    <div class="relative">
                        <input id="default_commission_percent" name="default_commission_percent"
                               type="number" step="0.01" min="0" max="100" required
                               value="{{ old('default_commission_percent', $partner->exists ? $partner->default_commission_percent : '10') }}"
                               @class([
                                   'nums w-full rounded-xl border bg-surface px-4 py-2.5 pr-10 text-ink focus:outline-none focus:ring-2 focus:ring-brand/40',
                                   'border-overdue' => $errors->has('default_commission_percent'),
                                   'border-line focus:border-brand' => ! $errors->has('default_commission_percent'),
                               ])>
                        <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-sm text-ink-muted">%</span>
                    </div>
                    @error('default_commission_percent')
                        <p class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- The single most important behaviour in the money model, stated
                 where the person changing the number will actually read it. --}}
            <div class="mt-5 rounded-xl border border-info/30 bg-info/5 p-4">
                <p class="text-sm font-medium text-ink">This rate applies to new projects only</p>
                <p class="mt-1 text-sm leading-relaxed text-ink-soft">
                    Every project stores the rate it was agreed at. Changing this never
                    alters commission already owed on existing work — and you can set a
                    different rate on any individual project.
                </p>
            </div>

            <div class="mt-5">
                <x-ui.field name="notes" label="Notes" type="textarea" :value="old('notes', $partner->notes)"
                            help="Deal terms, how you met, payment preferences." />
            </div>

            <label class="mt-5 flex items-center gap-2.5 text-sm text-ink">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $partner->exists ? $partner->is_active : true))
                       class="h-4 w-4 rounded border-line text-brand focus:ring-brand/40">
                Active partner
            </label>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit"
                    class="rounded-xl bg-brand px-6 py-2.5 font-semibold text-white transition hover:bg-brand-hover">
                {{ $editing ? 'Save changes' : 'Add partner' }}
            </button>

            <a href="{{ $editing ? route('admin.partners.show', $partner) : route('admin.partners.index') }}"
               class="px-2 py-2.5 text-sm text-ink-muted hover:text-ink">Cancel</a>

            @if ($editing)
                @can('delete', $partner)
                    <span class="ml-auto">
                        <button type="submit" form="delete-partner"
                                class="text-sm font-medium text-overdue hover:underline"
                                onclick="return confirm('Delete {{ addslashes($partner->name) }}? This cannot be undone.')">
                            Delete partner
                        </button>
                    </span>
                @endcan
            @endif
        </div>
    </form>

    @if ($editing)
        @can('delete', $partner)
            <form id="delete-partner" method="POST" action="{{ route('admin.partners.destroy', $partner) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endcan
    @endif
</x-layouts.admin>
