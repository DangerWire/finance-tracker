@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-[#0b0e14] border border-white/15 text-slate-100 placeholder-slate-500 rounded-none clip-chamfer text-sm font-mono focus:border-[#FFE500] focus:ring-1 focus:ring-[#FFE500] focus:outline-none shadow-inner transition duration-150']) }}>

