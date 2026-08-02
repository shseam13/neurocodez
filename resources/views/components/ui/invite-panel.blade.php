@props(['action', 'users', 'label', 'blurb'])

{{-- Portal invitations live on the client's or partner's own page so the account
     is always linked to the right record — inviting from a generic screen makes
     it easy to attach someone to the wrong one. --}}
<div class="surface p-5">
    <h2 class="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</h2>
    <p class="mb-4 text-xs text-ink-muted">{{ $blurb }}</p>

    @forelse ($users as $user)
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-line pb-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-ink">{{ $user->name }}</p>
                <p class="truncate text-xs text-ink-muted">{{ $user->email }}</p>
            </div>
            <span class="shrink-0">
                @if ($user->hasPendingInvitation())
                    <span class="badge badge-due">Pending</span>
                @elseif (! $user->is_active)
                    <span class="badge badge-overdue">Off</span>
                @else
                    <span class="badge badge-paid">Active</span>
                @endif
            </span>
        </div>
    @empty
        <p class="mb-4 text-sm text-ink-soft">No login yet.</p>
    @endforelse

    @can('manageUsers')
        <form method="POST" action="{{ $action }}" class="space-y-2">
            @csrf
            <input type="text" name="name" placeholder="Their name" required
                   class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
            <input type="email" name="email" placeholder="Their email" required
                   class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
            <button type="submit"
                    class="w-full rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                Send invitation
            </button>
        </form>

        @error('email')<p class="mt-2 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
        @error('invite')<p class="mt-2 text-xs font-medium text-overdue">{{ $message }}</p>@enderror
    @endcan
</div>
