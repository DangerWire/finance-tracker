@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-zinc-900/80 border border-zinc-800 text-zinc-100 placeholder-zinc-500 rounded-lg text-sm focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500 focus:outline-none shadow-inner transition duration-150']) }}>
