<x-layouts.public
    title="About"
    description="Neuro Codez builds websites, web applications and brand identities — and teaches the craft on YouTube.">

    <section class="relative overflow-hidden border-b border-line">
        <div class="neural-pattern neural-pattern-fade" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-4xl px-5 py-16">
            <x-brand.lockup :size="52" slogan />

            <h1 class="mt-10 text-3xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">
                Software built properly, explained openly.
            </h1>

            <p class="mt-6 max-w-2xl text-lg leading-relaxed text-ink-soft">
                Neuro Codez designs and builds websites, web applications and brand identities.
                We also publish free tutorials so anyone can learn the same craft — because the
                two go together.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-5 py-14">
        <div class="grid gap-5 sm:grid-cols-3">
            @foreach ([
                ['Connect', 'We start by understanding the problem, not by writing code. A clear scope beats a fast start.'],
                ['Create', 'Design and engineering together — accessible, fast, and built to be maintained.'],
                ['Serve', 'We stay after launch. You get a portal to track progress, files and invoices.'],
            ] as [$heading, $body])
                <div class="glass p-6">
                    <h2 class="font-bold text-brand-text">{{ $heading }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $body }}</p>
                </div>
            @endforeach
        </div>

        <div class="glass relative mt-10 overflow-hidden p-10 text-center">
            <div class="arc-rings"></div>
            <h2 class="text-2xl font-bold tracking-tight text-ink">Work with us</h2>
            <p class="mx-auto mt-3 max-w-md text-ink-soft">
                Tell us what you're building and we'll tell you honestly whether we're the right fit.
            </p>
            <a href="{{ route('public.contact') }}"
               class="mt-7 inline-block rounded-xl bg-brand px-7 py-3 font-semibold text-white transition hover:bg-brand-hover">
                Get in touch
            </a>
        </div>
    </section>
</x-layouts.public>
