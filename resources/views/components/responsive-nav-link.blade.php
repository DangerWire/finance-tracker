@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full px-3 py-2 rounded-lg text-start text-sm font-medium text-white bg-white/[0.08] transition duration-150 ease-in-out'
            : 'block w-full px-3 py-2 rounded-lg text-start text-sm font-medium text-zinc-400 hover:text-zinc-100 hover:bg-white/[0.04] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
