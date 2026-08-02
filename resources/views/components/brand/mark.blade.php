@props(['size' => 32])

{{--
    Inlined rather than <img> so the mark inherits `currentColor` — one SVG
    serves both themes and every context (sidebar, invoice, login) without a
    separate white/purple asset per placement.
--}}
@php
    // Small local file; the OS page cache makes this effectively free, and
    // reading it means a re-run of `node tools/brand/build-assets.mjs` is
    // picked up with no code change.
    $svg = trim(file_get_contents(public_path('brand/logo-mark.svg')));
    $svg = preg_replace('/^<svg\s/', '<svg ' . $attributes->merge([
        'width' => $size,
        'height' => $size,
        'class' => 'shrink-0',
    ])->toHtml() . ' ', $svg, 1);
@endphp

{!! $svg !!}
