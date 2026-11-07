@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-mono text-xs uppercase tracking-wider text-slate-300 mb-1']) }}>
    {{ $value ?? $slot }}
</label>

