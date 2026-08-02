@php($editing = $client->exists)

<x-layouts.admin :title="$editing ? 'Edit '.$client->name : 'Add client'"
                 :heading="$editing ? 'Edit '.$client->name : 'Add client'">

    <form method="POST"
          action="{{ $editing ? route('admin.clients.update', $client) : route('admin.clients.store') }}"
          class="mx-auto max-w-2xl">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="surface p-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field name="name" label="Name" required :value="old('name', $client->name)" />
                <x-ui.field name="company" label="Company" :value="old('company', $client->company)" />
                <x-ui.field name="email" label="Email" type="email" :value="old('email', $client->email)" />
                <x-ui.field name="phone" label="Phone" :value="old('phone', $client->phone)" />
            </div>

            <div class="mt-5">
                <label for="partner_id" class="mb-1.5 block text-sm font-medium text-ink">Referred by</label>
                <select id="partner_id" name="partner_id"
                        class="w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40">
                    <option value="">Nobody — direct client</option>
                    @foreach ($partners as $partner)
                        <option value="{{ $partner->id }}"
                                @selected(old('partner_id', $client->partner_id) == $partner->id)>
                            {{ $partner->name }} ({{ \App\Support\Percent::withSign($partner->default_commission_percent) }} default)
                        </option>
                    @endforeach
                </select>
                {{-- The distinction that trips people up, so it is spelled out. --}}
                <p class="mt-1.5 text-xs text-ink-muted">
                    This only pre-fills new projects. Each project keeps its own agreed rate,
                    so changing this never alters commission already owed.
                </p>
            </div>

            <div class="mt-5">
                <x-ui.field name="address" label="Address" type="textarea" :value="old('address', $client->address)" />
            </div>

            <div class="mt-5">
                <x-ui.field name="notes" label="Internal notes" type="textarea"
                            :value="old('notes', $client->notes)"
                            help="Only staff see this — never shown in the client portal." />
            </div>

            <label class="mt-5 flex items-center gap-2.5 text-sm text-ink">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $client->exists ? $client->is_active : true))
                       class="h-4 w-4 rounded border-line text-brand focus:ring-brand/40">
                Active client
            </label>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit"
                    class="rounded-xl bg-brand px-6 py-2.5 font-semibold text-white transition hover:bg-brand-hover">
                {{ $editing ? 'Save changes' : 'Add client' }}
            </button>

            <a href="{{ $editing ? route('admin.clients.show', $client) : route('admin.clients.index') }}"
               class="px-2 py-2.5 text-sm text-ink-muted hover:text-ink">Cancel</a>

            @if ($editing)
                @can('delete', $client)
                    <span class="ml-auto">
                        <button type="submit"
                                form="delete-client"
                                class="text-sm font-medium text-overdue hover:underline"
                                onclick="return confirm('Delete {{ addslashes($client->name) }}? This cannot be undone.')">
                            Delete client
                        </button>
                    </span>
                @endcan
            @endif
        </div>
    </form>

    @if ($editing)
        @can('delete', $client)
            {{-- Separate form: a delete button must never sit inside the edit
                 form, or a stray Enter key could submit the wrong action. --}}
            <form id="delete-client" method="POST" action="{{ route('admin.clients.destroy', $client) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endcan
    @endif
</x-layouts.admin>
