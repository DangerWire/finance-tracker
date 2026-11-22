<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-zinc-100 hover:bg-white text-zinc-950 font-medium text-xs rounded-lg shadow-sm hover:shadow transition duration-150 ease-in-out disabled:opacity-40 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>
