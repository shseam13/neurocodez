@props(['size' => 32, 'slogan' => false, 'tag' => 'div'])

<{{ $tag }} {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <x-brand.mark :size="$size" class="text-brand" />

    <span class="flex flex-col leading-none">
        <span class="font-bold tracking-tight text-ink" style="font-size: {{ $size * 0.56 }}px">
            NEURO&#8209;CODEZ
        </span>

        @if ($slogan)
            <span class="mt-1 font-semibold uppercase text-brand-text"
                  style="font-size: {{ max(8, $size * 0.24) }}px; letter-spacing: 0.18em">
                Connect . Create . Serve
            </span>
        @endif
    </span>
</{{ $tag }}>
