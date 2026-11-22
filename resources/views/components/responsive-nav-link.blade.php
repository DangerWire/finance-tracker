@props(['active'])

@php
$isActive = (bool) ($active ?? false);
$classes = $isActive
            ? 'flex items-center justify-between w-full px-3 py-2 rounded-lg text-start text-sm font-medium text-white bg-white/[0.08] border border-white/[0.08] transition duration-150 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70'
            : 'flex items-center justify-between w-full px-3 py-2 rounded-lg text-start text-sm font-medium text-zinc-400 border border-transparent hover:text-zinc-100 hover:bg-white/[0.04] transition duration-150 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70';
@endphp

<a {{ $attributes->merge(['class' => $classes, 'aria-current' => $isActive ? 'page' : null]) }}>
    <span>{{ $slot }}</span>
    @if ($isActive)
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
    @endif
</a>
