<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-[#0c1017] border border-white/20 rounded-none clip-chamfer font-mono font-semibold text-xs text-slate-200 uppercase tracking-widest hover:bg-white/5 hover:text-[#FFE500] hover:border-[#FFE500]/50 active:bg-black focus:outline-none focus:ring-2 focus:ring-[#FFE500] focus:ring-offset-2 focus:ring-offset-[#07080b] disabled:opacity-25 disabled:cursor-not-allowed transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>

