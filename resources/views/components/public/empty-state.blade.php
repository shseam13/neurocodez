@props(['heading', 'body' => null, 'action' => null])

<div class="glass mx-auto max-w-md p-10 text-center">
    <x-brand.mark :size="44" class="mx-auto text-brand opacity-40" />
    <h2 class="mt-5 font-semibold text-ink">{{ $heading }}</h2>

    @if ($body)
        <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $body }}</p>
    @endif

    @if ($action)
        <div class="mt-5">{{ $action }}</div>
    @endif
</div>
