@php
    $current = app()->getLocale();
@endphp

<div class="flex items-center gap-2">
    <span class="sr-only">{{ __('Language') }}</span>
    @foreach (['en' => 'English', 'id' => 'Bahasa Indonesia'] as $code => $label)
        @if ($current === $code)
            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700" aria-current="true">
                {{ $label }}
            </span>
        @else
            <form method="POST" action="{{ route('locale.update', $code) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect" value="{{ url()->current() }}?{{ http_build_query(request()->query()) }}">
                <button type="submit" class="rounded-md px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                    {{ $label }}
                </button>
            </form>
        @endif
    @endforeach
</div>
