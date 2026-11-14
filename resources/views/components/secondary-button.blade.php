<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-zinc-900 border border-zinc-800 text-zinc-300 hover:bg-zinc-800 hover:text-white font-medium text-xs rounded-lg shadow-sm transition duration-150 ease-in-out disabled:opacity-40 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>
