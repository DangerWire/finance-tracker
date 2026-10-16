@php
    /**
     * The calendar grid itself, and nothing else.
     *
     * This is the fragment served for month navigation, and the browser swaps it
     * into the picker wholesale. It therefore must contain no form state: a
     * hidden input living here would be destroyed by the swap, leaving the
     * filter form pointing at detached nodes. The date inputs sit outside the
     * picker for that reason.
     *
     * The markup lives here rather than in the browser so that a fetched month
     * and a server-rendered one cannot drift apart.
     *
     * @var \Carbon\CarbonImmutable $calendarMonth
     * @var \Illuminate\Support\Collection<string, int> $activityByDay
     */
    $monthStart = $calendarMonth->copy()->startOfMonth();
    $monthEnd = $monthStart->copy()->endOfMonth();
    $today = \Carbon\CarbonImmutable::now()->format('Y-m-d');

    // Month navigation only moves the calendar. It deliberately drops the date
    // selection from the URL, because browsing is not the same as filtering.
    $browseQuery = fn (string $month): string => route('transactions.index', [
        ...request()->except(['page', 'month', 'date', 'from', 'to']),
        // Keeps the filter panel open across the navigation.
        'panel' => 1,
        'month' => $month,
    ]);

    $previousMonth = $browseQuery($monthStart->copy()->subMonthNoOverflow()->format('Y-m'));
    $nextMonth = $browseQuery($monthStart->copy()->addMonthNoOverflow()->format('Y-m'));
    $previousYear = $browseQuery($monthStart->copy()->subYearNoOverflow()->format('Y-m'));
    $nextYear = $browseQuery($monthStart->copy()->addYearNoOverflow()->format('Y-m'));

    $months = [];
    for ($offset = -24; $offset <= 24; $offset++) {
        $candidate = $monthStart->copy()->addMonthsNoOverflow($offset);
        $months[] = [
            'url' => $browseQuery($candidate->format('Y-m')),
            'label' => $candidate->isoFormat('MMM YYYY'),
            'current' => $candidate->format('Y-m') === $monthStart->format('Y-m'),
        ];
    }

    // The selection is normalised into a range, so painting only has to consider
    // two bounds whether the user picked a day or a span.
    $start = $day ?? $from ?? null;
    $end = $to ?? $day ?? null;

    $weekdayLabels = [];
    for ($offset = 0; $offset < 7; $offset++) {
        $weekdayLabels[] = $monthStart->copy()->startOfWeek(\Carbon\CarbonImmutable::MONDAY)
            ->addDays($offset)->isoFormat('ddd');
    }
@endphp

<div class="flex items-center justify-between gap-1">
    <a href="{{ $previousYear }}" data-calendar-nav="1" rel="prev-year" title="{{ __('Previous year') }}"
        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">&laquo;</a>
    <a href="{{ $previousMonth }}" data-calendar-nav="1" rel="prev" title="{{ __('Previous month') }}"
        class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700">&lsaquo;</a>

    {{-- Fixed width on purpose: a native select sizes itself to its longest
         option, which would otherwise stretch the card far past the
         seven-column grid below. No name attribute, so it is never submitted. --}}
    <select data-calendar-month-select aria-label="{{ __('Month') }}"
        class="w-24 cursor-pointer truncate border-0 bg-transparent p-0 text-center text-sm font-semibold text-gray-800 focus:ring-0">
        @foreach ($months as $month)
            <option value="{{ $month['url'] }}" @selected($month['current'])>{{ $month['label'] }}</option>
        @endforeach
    </select>

    <a href="{{ $nextMonth }}" data-calendar-nav="1" rel="next" title="{{ __('Next month') }}"
        class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700">&rsaquo;</a>
    <a href="{{ $nextYear }}" data-calendar-nav="1" rel="next-year" title="{{ __('Next year') }}"
        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">&raquo;</a>
</div>

<div class="mt-2 grid grid-cols-7 text-center text-[11px] font-medium uppercase tracking-wide text-gray-400">
    @foreach ($weekdayLabels as $label)
        <span>{{ $label }}</span>
    @endforeach
</div>

<div class="mt-1 grid grid-cols-7 gap-0.5">
    @for ($blank = $monthStart->dayOfWeekIso - 1; $blank > 0; $blank--)
        <span aria-hidden="true" class="h-8"></span>
    @endfor

    @for ($number = 1; $number <= $monthEnd->day; $number++)
        @php
            $date = $monthStart->copy()->day($number)->format('Y-m-d');
            $isEdge = $date === $start || $date === $end;
            $inside = $start !== null && $end !== null && $date > $start && $date < $end;
        @endphp

        <button type="button" data-date="{{ $date }}" title="{{ $date }}"
            @class([
                'relative flex h-8 items-center justify-center rounded text-sm transition',
                'bg-indigo-600 font-semibold text-white' => $isEdge,
                'bg-indigo-100 text-indigo-900' => $inside,
                'text-gray-700 hover:bg-gray-200' => ! $isEdge && ! $inside,
                'ring-1 ring-inset ring-gray-400' => $date === $today && ! $isEdge,
            ])
            aria-pressed="{{ $isEdge ? 'true' : 'false' }}">
            <span>{{ $number }}</span>

            {{-- A mark on days that actually hold transactions, so the
                 calendar doubles as a density map of the month. --}}
            @if (($activityByDay[$date] ?? 0) > 0)
                <span @class([
                    'absolute bottom-0.5 h-1 w-1 rounded-full',
                    'bg-white' => $isEdge,
                    'bg-indigo-500' => ! $isEdge,
                ])></span>
            @endif
        </button>
    @endfor
</div>

<p class="mt-2 text-xs text-gray-500">
    @if ($start && $end && $start !== $end)
        {{ __('Click another date to start over.') }}
    @elseif ($start)
        {{ __('Click a second date for a range.') }}
    @else
        {{ __('Click a day, then a second day for a range.') }}
    @endif
</p>