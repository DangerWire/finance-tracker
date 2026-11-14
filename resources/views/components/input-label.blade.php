@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-medium text-zinc-400 mb-1.5']) }}>
    {{ $value ?? $slot }}
</label>
