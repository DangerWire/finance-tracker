@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-3 pt-1 border-b-2 border-[#FFE500] text-xs font-mono font-bold tracking-wider text-[#FFE500] glow-text-amber focus:outline-none transition duration-150 ease-in-out uppercase bg-[#FFE500]/5'
            : 'inline-flex items-center px-3 pt-1 border-b-2 border-transparent text-xs font-mono font-medium tracking-wider text-slate-400 hover:text-white hover:border-[#FFE500]/50 hover:bg-white/5 focus:outline-none focus:text-white transition duration-150 ease-in-out uppercase';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

