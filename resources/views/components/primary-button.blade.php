<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-[#FFE500] border border-[#FFE500] rounded-none clip-chamfer font-mono font-bold text-xs text-black uppercase tracking-widest hover:bg-white hover:border-white active:bg-[#e6ce00] focus:outline-none focus:ring-2 focus:ring-[#FFE500] focus:ring-offset-2 focus:ring-offset-[#07080b] glow-amber-btn transition ease-in-out duration-150 disabled:opacity-30 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>

