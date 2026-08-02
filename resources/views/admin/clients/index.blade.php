<x-layouts.admin title="Clients" heading="Clients">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        {{-- Plain GET search: bookmarkable, shareable, works without JS, and
             the browser back button behaves. --}}
        <form method="GET" action="{{ route('admin.clients.index') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $search }}"
                   placeholder="Search name, company, email, phone"
                   class="w-72 max-w-full rounded-xl border border-line bg-surface px-4 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40">
            <button type="submit" class="glass-flat rounded-xl px-4 py-2 text-sm font-medium text-ink">Search</button>
            @if ($search !== '')
                <a href="{{ route('admin.clients.index') }}" class="px-2 py-2 text-sm text-ink-muted hover:text-ink">Clear</a>
            @endif
        </form>

        @can('create', App\Models\Client::class)
            <a href="{{ route('admin.clients.create') }}"
               class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                Add client
            </a>
        @endcan
    </div>

    @error('client')
        <div class="mb-5 rounded-xl border border-overdue/40 bg-overdue/10 p-4 text-sm font-medium text-overdue">
            {{ $message }}
        </div>
    @enderror

    <div class="surface overflow-hidden">
        @if ($clients->isEmpty())
            <div class="p-12 text-center">
                <x-brand.mark :size="40" class="mx-auto text-brand opacity-40" />
                <h2 class="mt-5 font-semibold text-ink">
                    {{ $search !== '' ? 'No clients match that search' : 'No clients yet' }}
                </h2>
                <p class="mt-2 text-sm text-ink-soft">
                    {{ $search !== '' ? 'Try a different name or email.' : 'Add your first client to start tracking projects and payments.' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Client</th>
                            <th class="px-5 py-3 font-semibold">Contact</th>
                            <th class="px-5 py-3 font-semibold">Referred by</th>
                            <th class="px-5 py-3 text-right font-semibold">Projects</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr class="border-b border-line last:border-0">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.clients.show', $client) }}"
                                       class="font-medium text-ink hover:text-brand-text">{{ $client->name }}</a>
                                    @if ($client->company)
                                        <p class="text-xs text-ink-muted">{{ $client->company }}</p>
                                    @endif
                                    @unless ($client->is_active)
                                        <span class="badge badge-overdue mt-1">Inactive</span>
                                    @endunless
                                </td>
                                <td class="px-5 py-3 text-ink-soft">
                                    @if ($client->email)<p class="truncate">{{ $client->email }}</p>@endif
                                    @if ($client->phone)<p class="nums text-xs text-ink-muted">{{ $client->phone }}</p>@endif
                                </td>
                                <td class="px-5 py-3 text-ink-soft">
                                    {{ $client->partner?->name ?? '—' }}
                                </td>
                                <td class="nums px-5 py-3 text-right text-ink-soft">{{ $client->projects_count }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.clients.edit', $client) }}"
                                       class="text-xs font-medium text-brand-text hover:underline">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($clients->hasPages())
        <div class="mt-5">{{ $clients->links() }}</div>
    @endif
</x-layouts.admin>
