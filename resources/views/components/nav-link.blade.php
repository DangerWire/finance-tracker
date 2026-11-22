@props(['active'])

@php
$isActive = (bool) ($active ?? false);
$classes = $isActive
            ? 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-white bg-white/[0.08] border border-white/[0.1] shadow-sm transition duration-150 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70'
            : 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-zinc-400 border border-transparent hover:text-zinc-100 hover:bg-white/[0.04] transition duration-150 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/70';
@endphp

<a {{ $attributes->merge(['class' => $classes, 'aria-current' => $isActive ? 'page' : null]) }}>
    {{ $slot }}
</a>
