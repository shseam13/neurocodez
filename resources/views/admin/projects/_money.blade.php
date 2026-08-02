{{--
    Payments in and commission out, side by side on the project page.

    Both are solid surfaces, not glass: they are dense rows of figures, and the
    numbers have to be read exactly.
--}}

<section class="surface overflow-hidden">
    <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
        <h2 class="font-semibold text-ink">Payments received</h2>
        <span class="nums text-sm font-semibold text-paid">{{ $finance['paid']->formatWithCurrency() }}</span>
    </div>

    @forelse ($project->payments->sortByDesc('paid_at') as $payment)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="nums text-sm font-medium {{ $payment->amount->isNegative() ? 'text-overdue' : 'text-ink' }}">
                    {{ $payment->amount->format() }}
                    @if ($payment->amount->isNegative())
                        <span class="badge badge-overdue ml-1">Refund</span>
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-ink-muted">
                    {{ $payment->paid_at->format('j M Y') }} &middot; {{ $payment->method->label() }}
                    @if ($payment->reference) &middot; {{ $payment->reference }} @endif
                </p>
                @if ($payment->note)
                    <p class="mt-1 text-xs text-ink-soft">{{ $payment->note }}</p>
                @endif
            </div>

            @can('delete', $payment)
                <form method="POST" action="{{ route('admin.projects.payments.destroy', [$project, $payment]) }}"
                      onsubmit="return confirm('Remove this payment? The audit log keeps a record.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-ink-muted hover:text-overdue">Remove</button>
                </form>
            @endcan
        </div>
    @empty
        <p class="px-5 py-8 text-center text-sm text-ink-muted">Nothing received yet.</p>
    @endforelse

    @can('create', App\Models\Payment::class)
        <form method="POST" action="{{ route('admin.projects.payments.store', $project) }}"
              class="border-t border-line bg-surface-alt px-5 py-4">
            @csrf
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Record a payment</p>

            <div class="grid gap-3 sm:grid-cols-2">
                <input type="number" name="amount" step="0.01" required placeholder="Amount"
                       class="nums rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">

                <input type="date" name="paid_at" required value="{{ now()->toDateString() }}"
                       class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">

                <select name="method"
                        class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">
                    @foreach (\App\Enums\PaymentMethod::cases() as $method)
                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                    @endforeach
                </select>

                <input type="text" name="reference" placeholder="Transaction ID (optional)"
                       class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
            </div>

            <div class="mt-3 flex items-center gap-3">
                <p class="text-xs text-ink-muted">
                    Use a negative amount to record a refund.
                </p>
                <button type="submit"
                        class="ml-auto rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                    Record payment
                </button>
            </div>

            @error('amount')
                <p class="mt-2 text-xs font-medium text-overdue">{{ $message }}</p>
            @enderror
        </form>
    @endcan
</section>

@if ($commission && $commission['applies'])
    <section class="surface overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
            <h2 class="font-semibold text-ink">Commission paid out</h2>
            <span class="nums text-sm font-semibold {{ $commission['due']->isPositive() ? 'text-overdue' : 'text-ink-muted' }}">
                {{ $commission['due']->formatWithCurrency() }} owed
            </span>
        </div>

        @forelse ($project->commissionPayouts->sortByDesc('paid_at') as $payout)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-0">
                <div>
                    <p class="nums text-sm font-medium text-ink">{{ $payout->amount->format() }}</p>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        {{ $payout->paid_at->format('j M Y') }} &middot; {{ $payout->method->label() }}
                        @if ($payout->reference) &middot; {{ $payout->reference }} @endif
                    </p>
                </div>

                @can('delete', $payout)
                    <form method="POST" action="{{ route('admin.projects.payouts.destroy', [$project, $payout]) }}"
                          onsubmit="return confirm('Remove this payout record?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-ink-muted hover:text-overdue">Remove</button>
                    </form>
                @endcan
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-ink-muted">
                Nothing paid to {{ $project->partner->name }} on this project yet.
            </p>
        @endforelse

        @can('create', App\Models\CommissionPayout::class)
            <form method="POST" action="{{ route('admin.projects.payouts.store', $project) }}"
                  class="border-t border-line bg-surface-alt px-5 py-4">
                @csrf
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                    Pay {{ $project->partner->name }}
                </p>

                <div class="grid gap-3 sm:grid-cols-2">
                    {{-- Pre-filled with exactly what is outstanding, which is
                         the amount being paid almost every time. --}}
                    <input type="number" name="amount" step="0.01" required
                           value="{{ $commission['due']->isPositive() ? $commission['due']->toMajor() : '' }}"
                           placeholder="Amount"
                           class="nums rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">

                    <input type="date" name="paid_at" required value="{{ now()->toDateString() }}"
                           class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">

                    <select name="method"
                            class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>

                    <input type="text" name="reference" placeholder="Transaction ID (optional)"
                           class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none">
                </div>

                <button type="submit"
                        class="mt-3 w-full rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover sm:w-auto">
                    Record payout
                </button>
            </form>
        @endcan
    </section>
@endif
