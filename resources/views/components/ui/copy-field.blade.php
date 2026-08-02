@props(['value', 'label' => null, 'help' => null, 'rows' => null])

@php
    // Unique per instance: a page can show several invitation links at once and
    // each Copy button has to target its own field.
    $id = 'copy-'.\Illuminate\Support\Str::random(8);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-xs font-medium text-ink-soft">{{ $label }}</label>
    @endif

    {{-- readonly, not disabled: the value still has to be selectable and
         copyable by hand if the Clipboard API is unavailable. --}}
    @if ($rows)
        <textarea id="{{ $id }}" rows="{{ $rows }}" readonly onfocus="this.select()"
                  class="w-full resize-y rounded-lg border border-line bg-surface px-3 py-2 text-xs leading-relaxed text-ink-soft focus:border-brand focus:outline-none">{{ $value }}</textarea>

        <button type="button" data-copy-target="#{{ $id }}"
                class="rounded-lg border border-line px-3 py-2 text-xs font-semibold text-ink transition hover:border-brand hover:text-brand">
            Copy message
        </button>
    @else
        <div class="flex gap-2">
            <input id="{{ $id }}" type="text" readonly value="{{ $value }}" onfocus="this.select()"
                   class="min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 py-2 font-mono text-xs text-ink-soft focus:border-brand focus:outline-none">

            <button type="button" data-copy-target="#{{ $id }}"
                    class="shrink-0 rounded-lg border border-line px-3 py-2 text-xs font-semibold text-ink transition hover:border-brand hover:text-brand">
                Copy
            </button>
        </div>
    @endif

    @if ($help)
        <p class="text-xs text-ink-muted">{{ $help }}</p>
    @endif
</div>
