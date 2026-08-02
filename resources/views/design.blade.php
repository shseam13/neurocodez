<x-layouts.app title="Design System">
    <div class="mx-auto max-w-5xl px-5 py-10">

        <header class="mb-10 flex items-center justify-between gap-4">
            <x-brand.lockup :size="44" slogan />
            <x-ui.theme-toggle />
        </header>

        {{-- Glass belongs here: a small number of large containers. --}}
        <section class="mb-8 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['Receivable', '50,000', 'paid', '3 active projects'],
                ['Outstanding', '30,000', 'due', 'across 2 clients'],
                ['Commissions payable', '2,000', 'overdue', '1 partner'],
            ] as [$label, $value, $tone, $meta])
                <div class="glass p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</p>
                    <p class="nums mt-2 text-3xl font-bold text-ink">
                        <span class="text-base font-medium text-ink-soft">BDT</span> {{ $value }}
                    </p>
                    <p class="mt-2 text-sm text-{{ $tone === 'paid' ? 'paid' : ($tone === 'due' ? 'due-soon' : 'overdue') }}">
                        {{ $meta }}
                    </p>
                </div>
            @endforeach
        </section>

        {{-- Solid, not glass: dense, scrollable, and full of numbers that must
             be read exactly. Also keeps backdrop-filter off repeating rows. --}}
        <section class="surface mb-8 overflow-hidden">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 class="font-semibold text-ink">Outstanding payments</h2>
                <span class="text-xs text-ink-muted">solid surface — no blur</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Client</th>
                            <th class="px-5 py-3 font-semibold">Project</th>
                            <th class="px-5 py-3 font-semibold">Stage</th>
                            <th class="px-5 py-3 text-right font-semibold">Agreed</th>
                            <th class="px-5 py-3 text-right font-semibold">Due</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            ['Rahim Traders', 'Client portal v2', 'Development', '50,000', '30,000', 'due', 'Due 10 Aug'],
                            ['Karim Enterprise', 'Brand identity', 'Final files', '18,500', '0', 'paid', 'Paid'],
                            ['Shirin Foods', 'Landing page', 'Review', '12,000', '12,000', 'overdue', 'Overdue 6d'],
                        ] as [$client, $project, $stage, $agreed, $due, $tone, $status])
                            <tr class="border-b border-line last:border-0">
                                <td class="px-5 py-3 font-medium text-ink">{{ $client }}</td>
                                <td class="px-5 py-3 text-ink-soft">{{ $project }}</td>
                                <td class="px-5 py-3 text-ink-soft">{{ $stage }}</td>
                                <td class="nums px-5 py-3 text-right text-ink-soft">{{ $agreed }}</td>
                                <td class="nums px-5 py-3 text-right font-semibold text-ink">{{ $due }}</td>
                                <td class="px-5 py-3">
                                    <span class="badge badge-{{ $tone }}">{{ $status }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
            <div class="glass p-5">
                <h2 class="mb-3 font-semibold text-ink">Brand colour rule</h2>
                <p class="mb-4 text-sm text-ink-soft">
                    <code class="text-brand-text">#914EE9</code> is a fill, never body text —
                    it measures 3.90:1 on the dark canvas. Text uses
                    <code class="text-brand-text">--brand-text</code>, tinted per theme.
                </p>
                <div class="flex flex-wrap gap-2">
                    <button class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                        Primary
                    </button>
                    <button class="glass-flat rounded-lg px-4 py-2 text-sm font-semibold text-ink transition hover:text-brand-text">
                        Secondary
                    </button>
                    <a href="#" class="px-2 py-2 text-sm font-semibold text-brand-text underline-offset-4 hover:underline">
                        A link
                    </a>
                </div>
            </div>

            <div class="glass relative overflow-hidden p-5">
                <div class="arc-rings"></div>
                <h2 class="mb-3 font-semibold text-ink">Arc rings</h2>
                <p class="text-sm text-ink-soft">
                    Lifted from the app icon's glow ring. Login and dashboard hero only —
                    decorative, never behind data.
                </p>
            </div>
        </section>

    </div>
</x-layouts.app>
