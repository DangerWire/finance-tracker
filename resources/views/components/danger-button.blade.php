<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-medium text-xs rounded-lg shadow-sm transition duration-150 ease-in-out disabled:opacity-40 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>
