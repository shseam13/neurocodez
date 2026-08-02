<x-layouts.public
    title="Our work"
    description="Selected projects designed, built and shipped by Neuro Codez.">

    <section class="relative overflow-hidden border-b border-line">
        <div class="neural-pattern neural-pattern-fade" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-6xl px-5 py-16">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-5xl">Our work</h1>
            <p class="mt-4 max-w-xl text-lg text-ink-soft">
                Projects we've designed, built and shipped — for businesses, teams and founders.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-14">
        @if ($items->isEmpty())
            <x-public.empty-state
                heading="Case studies coming soon"
                body="We're writing up recent projects. In the meantime, get in touch to hear what we've been building.">
                <x-slot:action>
                    <a href="{{ route('public.contact') }}"
                       class="inline-block rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-hover">
                        Talk to us
                    </a>
                </x-slot:action>
            </x-public.empty-state>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <x-public.work-card :item="$item" />
                @endforeach
            </div>

            <div class="mt-10">{{ $items->links() }}</div>
        @endif
    </section>
</x-layouts.public>
