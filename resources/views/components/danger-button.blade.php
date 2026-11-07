<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-[#FF3366] border border-[#FF3366] rounded-none clip-chamfer font-mono font-bold text-xs text-white uppercase tracking-widest hover:bg-[#ff1f57] active:bg-[#d91d4e] focus:outline-none focus:ring-2 focus:ring-[#FF3366] focus:ring-offset-2 focus:ring-offset-[#07080b] transition ease-in-out duration-150 disabled:opacity-30 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>

