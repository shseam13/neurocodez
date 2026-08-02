<x-layouts.admin title="Enquiries" heading="Enquiries">
    <div class="mb-5 flex flex-wrap items-center gap-2">
        @foreach ([
            '' => 'To handle ('.$counts['actionable'].')',
            'new' => 'New ('.$counts['new'].')',
            'converted' => 'Converted',
            'spam' => 'Spam',
        ] as $value => $label)
            <a href="{{ route('admin.leads.index', $value !== '' ? ['status' => $value] : []) }}"
               @class([
                   'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                   'bg-brand text-white' => $status === $value,
                   'glass-flat text-ink-soft hover:text-ink' => $status !== $value,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    @if ($leads->isEmpty())
        <div class="surface p-12 text-center">
            <x-brand.mark :size="40" class="mx-auto text-brand opacity-40" />
            <h2 class="mt-5 font-semibold text-ink">Nothing here</h2>
            <p class="mt-2 text-sm text-ink-soft">
                Enquiries from the website contact form land here instead of getting lost in email.
            </p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($leads as $lead)
                <article class="surface p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-semibold text-ink">
                                {{ $lead->name }}
                                <span class="badge badge-{{ match ($lead->status) {
                                    'new' => 'info', 'contacted' => 'due',
                                    'converted' => 'paid', default => 'overdue',
                                } }} ml-1">{{ ucfirst($lead->status) }}</span>
                            </h2>

                            <p class="mt-1 flex flex-wrap gap-x-3 text-xs text-ink-muted">
                                @if ($lead->email)
                                    <a href="mailto:{{ $lead->email }}" class="text-brand-text hover:underline">{{ $lead->email }}</a>
                                @endif
                                @if ($lead->phone)
                                    <a href="tel:{{ $lead->phone }}" class="nums text-brand-text hover:underline">{{ $lead->phone }}</a>
                                @endif
                                <span>{{ $lead->created_at->diffForHumans() }}</span>
                            </p>
                        </div>

                        @if ($lead->convertedClient)
                            <a href="{{ route('admin.clients.show', $lead->convertedClient) }}"
                               class="shrink-0 text-xs font-medium text-brand-text hover:underline">
                                View client &rarr;
                            </a>
                        @endif
                    </div>

                    @if ($lead->subject)
                        <p class="mt-3 text-sm font-medium text-ink">{{ $lead->subject }}</p>
                    @endif

                    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-soft">{{ $lead->message }}</p>

                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-3">
                        @can('convert', $lead)
                            @unless ($lead->convertedClient)
                                <form method="POST" action="{{ route('admin.leads.convert', $lead) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-hover">
                                        Convert to client
                                    </button>
                                </form>
                            @endunless
                        @endcan

                        @can('update', $lead)
                            @foreach (['contacted' => 'Mark contacted', 'spam' => 'Mark spam', 'new' => 'Reopen'] as $value => $label)
                                @if ($lead->status !== $value)
                                    <form method="POST" action="{{ route('admin.leads.update', $lead) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="{{ $value }}">
                                        <button type="submit" class="text-xs text-ink-muted hover:text-ink">{{ $label }}</button>
                                    </form>
                                @endif
                            @endforeach
                        @endcan
                    </div>
                </article>
            @endforeach
        </div>

        @if ($leads->hasPages())
            <div class="mt-5">{{ $leads->links() }}</div>
        @endif
    @endif
</x-layouts.admin>
