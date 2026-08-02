<x-layouts.public
    title="Contact"
    description="Tell us about your project and we'll come back with a clear scope, price and delivery date.">

    <section class="relative overflow-hidden border-b border-line">
        <div class="neural-pattern neural-pattern-fade" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-6xl px-5 py-16">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-5xl">Start a project</h1>
            <p class="mt-4 max-w-xl text-lg text-ink-soft">
                Tell us what you need. We'll reply with a clear scope, a fixed price and a delivery date.
            </p>
        </div>
    </section>

    <section class="mx-auto grid max-w-6xl gap-10 px-5 py-14 lg:grid-cols-[1.4fr_1fr]">
        <div class="surface p-6 sm:p-8">
            @if (session('sent'))
                <div class="mb-6 rounded-xl border border-paid/40 bg-paid/10 p-4">
                    <p class="font-semibold text-paid">Thank you — your message is with us.</p>
                    <p class="mt-1 text-sm text-ink-soft">We usually reply within one working day.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('public.contact.store') }}" class="space-y-5">
                @csrf

                {{-- Honeypot. Hidden from people and from screen readers; bots
                     that fill every field give themselves away. --}}
                <div class="hidden" aria-hidden="true">
                    <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field name="name" label="Your name" required :value="old('name')" />
                    <x-ui.field name="email" label="Email" type="email" :value="old('email')" />
                    <x-ui.field name="phone" label="Phone / WhatsApp" :value="old('phone')" />
                    <x-ui.field name="subject" label="Subject" :value="old('subject')" />
                </div>

                <x-ui.field name="message" label="What do you need?" type="textarea" required
                            :value="old('message')"
                            placeholder="A short description of the project, your budget range and your timeline." />

                <button type="submit"
                        class="w-full rounded-xl bg-brand px-6 py-3 font-semibold text-white transition hover:bg-brand-hover sm:w-auto">
                    Send message
                </button>

                <p class="text-xs text-ink-muted">
                    Leave either an email address or a phone number so we can reply.
                </p>
            </form>
        </div>

        <aside class="space-y-4">
            @php $site = config('neuro.site'); @endphp

            <div class="glass p-6">
                <h2 class="font-semibold text-ink">Reach us directly</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    @if ($site['email'])
                        <li>
                            <span class="block text-xs uppercase tracking-wider text-ink-muted">Email</span>
                            <a href="mailto:{{ $site['email'] }}" class="text-brand-text hover:underline">{{ $site['email'] }}</a>
                        </li>
                    @endif
                    @if ($site['phone'])
                        <li>
                            <span class="block text-xs uppercase tracking-wider text-ink-muted">Phone</span>
                            <a href="tel:{{ $site['phone'] }}" class="text-brand-text hover:underline">{{ $site['phone'] }}</a>
                        </li>
                    @endif
                    @if ($site['whatsapp'])
                        <li>
                            <span class="block text-xs uppercase tracking-wider text-ink-muted">WhatsApp</span>
                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $site['whatsapp']) }}"
                               target="_blank" rel="noopener noreferrer"
                               class="text-brand-text hover:underline">{{ $site['whatsapp'] }}</a>
                        </li>
                    @endif
                </ul>
            </div>

            <div class="glass p-6">
                <h2 class="font-semibold text-ink">What happens next</h2>
                <ol class="mt-4 space-y-3 text-sm text-ink-soft">
                    <li><span class="font-semibold text-brand-text">1.</span> We read your message and reply with questions.</li>
                    <li><span class="font-semibold text-brand-text">2.</span> You get a written scope, a fixed price and a date.</li>
                    <li><span class="font-semibold text-brand-text">3.</span> We build it, and you track progress in your own portal.</li>
                </ol>
            </div>
        </aside>
    </section>
</x-layouts.public>
