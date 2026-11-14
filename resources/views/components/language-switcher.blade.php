@php
    $current = app()->getLocale();
@endphp

<div class="flex items-center gap-1 text-xs">
    <span class="sr-only">{{ __('Language') }}</span>
    @foreach (['en' => 'English', 'id' => 'Bahasa Indonesia'] as $code => $label)
        @if ($current === $code)
            <span class="px-2.5 py-1 bg-white/[0.08] text-zinc-100 border border-white/[0.08] font-medium rounded-md text-[11px]" aria-current="true">
                {{ $label }}
            </span>
        @else
            <form method="POST" action="{{ route('locale.update', $code) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect" value="{{ url()->current() }}?{{ http_build_query(request()->query()) }}">
                <button type="submit" class="px-2.5 py-1 text-zinc-400 hover:text-zinc-200 hover:bg-white/[0.04] border border-transparent font-medium rounded-md text-[11px] transition-colors">
                    {{ $label }}
                </button>
            </form>
        @endif
    @endforeach
</div>
