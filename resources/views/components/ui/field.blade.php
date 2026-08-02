@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'help' => null,
])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    // Point screen readers at the error or help text for this field.
    $describedBy = collect([
        $hasError ? "{$id}-error" : null,
        $help ? "{$id}-help" : null,
    ])->filter()->implode(' ');
@endphp

<div class="{{ $type === 'textarea' ? 'sm:col-span-2' : '' }}">
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">
        {{ $label }}
        @if ($required)
            <span class="text-overdue" aria-hidden="true">*</span>
        @endif
    </label>

    @php
        $classes = 'w-full rounded-xl border bg-surface px-4 py-2.5 text-ink placeholder:text-ink-muted
                    transition focus:outline-none focus:ring-2 focus:ring-brand/40 '
            .($hasError ? 'border-overdue' : 'border-line focus:border-brand');
    @endphp

    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="6"
                  @if ($required) required @endif
                  @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                  @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                  @if ($hasError) aria-invalid="true" @endif
                  class="{{ $classes }}">{{ $value }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
               @if ($required) required @endif
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @if ($hasError) aria-invalid="true" @endif
               class="{{ $classes }}">
    @endif

    @if ($help)
        <p id="{{ $id }}-help" class="mt-1.5 text-xs text-ink-muted">{{ $help }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs font-medium text-overdue">{{ $message }}</p>
    @enderror
</div>
