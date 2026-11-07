@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-4 pe-4 py-2 border-l-4 border-[#FFE500] text-start text-xs font-mono font-bold tracking-wider uppercase text-[#FFE500] bg-[#FFE500]/10 focus:outline-none transition duration-150 ease-in-out'
            : 'block w-full ps-4 pe-4 py-2 border-l-4 border-transparent text-start text-xs font-mono font-medium tracking-wider uppercase text-slate-400 hover:text-white hover:bg-white/5 hover:border-white/20 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

