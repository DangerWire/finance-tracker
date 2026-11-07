@php
    $current = app()->getLocale();
@endphp

<div class="flex items-center gap-1.5 font-mono text-xs">
    <span class="sr-only">{{ __('Language') }}</span>
    @foreach (['en' => 'English', 'id' => 'Bahasa Indonesia'] as $code => $label)
        @if ($current === $code)
            <span class="px-2.5 py-1 bg-[#FFE500]/15 text-[#FFE500] border border-[#FFE500]/40 font-bold tracking-wider clip-chamfer text-[11px]" aria-current="true">
                {{ $label }}
            </span>
        @else
            <form method="POST" action="{{ route('locale.update', $code) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect" value="{{ url()->current() }}?{{ http_build_query(request()->query()) }}">
                <button type="submit" class="px-2.5 py-1 text-slate-400 hover:text-white hover:bg-white/5 border border-transparent hover:border-white/10 font-medium tracking-wider clip-chamfer text-[11px] transition-colors">
                    {{ $label }}
                </button>
            </form>
        @endif
    @endforeach
</div>

