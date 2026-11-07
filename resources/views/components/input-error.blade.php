@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-xs font-mono text-[#FF3366] space-y-1 mt-1']) }}>
        @foreach ((array) $messages as $message)
            <li class="flex items-center gap-1.5">
                <span class="inline-block w-1.5 h-1.5 bg-[#FF3366]"></span>
                <span>{{ $message }}</span>
            </li>
        @endforeach
    </ul>
@endif

