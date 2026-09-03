@props([
    'fixtureType',
    'size' => 56,
])

@php
    $iconUrl = $fixtureType->iconUrl();
@endphp

<span
    {{ $attributes->class('d-inline-flex align-items-center justify-content-center rounded border bg-light overflow-hidden flex-shrink-0') }}
    style="width: {{ $size }}px; height: {{ $size }}px;"
>
    @if ($iconUrl)
        <img
            src="{{ $iconUrl }}"
            alt="{{ $fixtureType->name }}"
            width="{{ $size }}"
            height="{{ $size }}"
            style="object-fit: contain;"
            onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');"
        >
        <span class="d-none text-muted" aria-label="Icono no disponible">
            <i data-lucide="box"></i>
        </span>
    @else
        <span class="text-muted" aria-label="Elemento sin icono">
            <i data-lucide="box"></i>
        </span>
    @endif
</span>
