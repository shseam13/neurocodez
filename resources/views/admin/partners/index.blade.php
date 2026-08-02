<x-layouts.admin title="Partners" heading="Partners">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.partners.index') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, email, phone"
                   class="w-72 max-w-full rounded-xl border border-line bg-surface px-4 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/40">
            <button type="submit" class="glass-flat rounded-xl px-4 py-2 text-sm font-medium text-ink">Search</button>
            @if ($search !== '')
                <a href="{{ route('admin.partners.index') }}" class="px-2 py-2 text-sm text-ink-muted hover:text-ink">Clear</a>
            @endif
        </form>

        @can('create', App\Models\Partner::class)
            <a href="{{ route('admin.partners.create') }}"
               class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                Add partner
            </a>
        @endcan
    </div>

    @error('partner')
        <div class="mb-5 rounded-xl border border-overdue/40 bg-overdue/10 p-4 text-sm font-medium text-overdue">
            {{ $message }}
        </div>
    @enderror

    <div class="surface overflow-hidden">
        @if ($partners->isEmpty())
            <div class="p-12 text-center">
                <x-brand.mark :size="40" class="mx-auto text-brand opacity-40" />
                <h2 class="mt-5 font-semibold text-ink">
                    {{ $search !== '' ? 'No partners match that search' : 'No partners yet' }}
                </h2>
                <p class="mt-2 text-sm text-ink-soft">
                    Add the people who bring you work, each with their own agreed percentage.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Partner</th>
                            <th class="px-5 py-3 font-semibold">Contact</th>
                            <th class="px-5 py-3 text-right font-semibold">Default rate</th>
                            <th class="px-5 py-3 text-right font-semibold">Projects</th>
                            @if ($canSeeCommissions)
                                <th class="px-5 py-3 text-right font-semibold">You owe</th>
                            @endif
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($partners as $partner)
                            <tr class="border-b border-line last:border-0">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.partners.show', $partner) }}"
                                       class="font-medium text-ink hover:text-brand-text">{{ $partner->name }}</a>
                                    @unless ($partner->is_active)
                                        <span class="badge badge-overdue mt-1">Inactive</span>
                                    @endunless
                                </td>
                                <td class="px-5 py-3 text-ink-soft">
                                    @if ($partner->email)<p class="truncate">{{ $partner->email }}</p>@endif
                                    @if ($partner->phone)<p class="nums text-xs text-ink-muted">{{ $partner->phone }}</p>@endif
                                </td>
                                <td class="nums px-5 py-3 text-right text-ink-soft">
                                    {{ \App\Support\Percent::withSign($partner->default_commission_percent) }}
                                </td>
                                <td class="nums px-5 py-3 text-right text-ink-soft">{{ $partner->projects_count }}</td>
                                @if ($canSeeCommissions)
                                    <td class="nums px-5 py-3 text-right font-semibold {{ $due[$partner->id]->isPositive() ? 'text-overdue' : 'text-ink-muted' }}">
                                        {{ $due[$partner->id]->format() }}
                                    </td>
                                @endif
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.partners.edit', $partner) }}"
                                       class="text-xs font-medium text-brand-text hover:underline">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($partners->hasPages())
        <div class="mt-5">{{ $partners->links() }}</div>
    @endif
</x-layouts.admin>
