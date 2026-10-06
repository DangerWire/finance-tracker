<?php

namespace App\Http\Controllers;

use App\Actions\ConvertTransactionAmount;
use App\Enums\TransactionType;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TransactionController extends Controller
{
    /**
     * Display a listing of the authenticated user's transactions.
     *
     * The list can be grouped by day. When it is, pagination runs over the
     * distinct dates rather than over the transactions themselves, so a day is
     * never split across two pages and a group's count and total describe
     * every row that belongs to it.
     */
    public function index(Request $request, CategoryService $categories): View
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group' => ['nullable', 'in:0,1'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $grouped = ($filters['group'] ?? '1') === '1';
        [$from, $to] = $this->periodFrom($filters);

        $baseCurrency = strtoupper((string) config('finance.base_currency'));

        $days = null;
        $groups = collect();
        $transactions = null;

        if ($grouped) {
            $days = $this->applyFilters($request->user()->transactions()->getQuery(), $filters, $from, $to)
                ->selectRaw('DATE(occurred_at) as day')
                ->distinct()
                ->orderByDesc('day')
                ->paginate(15)
                ->withQueryString();

            $groups = $this->groupsFor($request->user(), $filters, $from, $to, $days->pluck('day')->all());
        } else {
            $transactions = $this->applyFilters($request->user()->transactions()->getQuery(), $filters, $from, $to)
                ->latest('occurred_at')
                ->paginate(15)
                ->withQueryString();
        }

        // Totals are summed from the frozen base snapshot rather than the
        // original amount, so mixed currencies never add up incorrectly. The
        // snapshot's currency is matched as well: a row converted into an
        // earlier base currency still carries a base_amount, and summing it
        // alongside current rows would blend two currencies into one figure.
        $summary = $request->user()->transactions()
            ->whereNotNull('base_amount')
            ->where('base_currency', $baseCurrency)
            ->selectRaw('type, SUM(base_amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        // Rounded to the precision the base snapshot is stored at, so totals do
        // not display float noise such as 16025.940000000002.
        $income = round((float) ($summary[TransactionType::Income->value] ?? 0), 4);
        $expenses = round((float) ($summary[TransactionType::Expense->value] ?? 0), 4);

        // Anything the cards cannot legitimately include: rows with no rate at
        // all, plus rows still sitting in a retired base currency.
        $excludedCount = $request->user()->transactions()
            ->where(fn ($query) => $query
                ->whereNull('base_amount')
                ->orWhere('base_currency', '!=', $baseCurrency))
            ->count();

        return view('transactions.index', [
            'transactions' => $transactions,
            'groups' => $groups,
            'days' => $days,
            'grouped' => $grouped,
            'filters' => $filters,
            'day' => $filters['date'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'calendarMonth' => $this->calendarMonth($filters),
            'activityByDay' => $this->activityByDay($request, $filters, $this->calendarMonth($filters)),
            'income' => $income,
            'expenses' => $expenses,
            'balance' => round($income - $expenses, 4),
            'baseCurrency' => $baseCurrency,
            'unconvertedCount' => $excludedCount,
            'types' => TransactionType::cases(),
            'categories' => $categories->forUser($request->user()->id),
        ]);
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create(Request $request, CategoryService $categories): View
    {
        return view('transactions.create', [
            'transaction' => new Transaction(['currency' => 'CNY', 'type' => TransactionType::Expense]),
            'types' => TransactionType::cases(),
            'categories' => $categories->forUser($request->user()->id),
        ]);
    }

    /**
     * Store a newly created transaction.
     */
    public function store(StoreTransactionRequest $request, ConvertTransactionAmount $convert): RedirectResponse
    {
        $attributes = $convert->apply($request->validated());

        $request->user()->transactions()->create($attributes);

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Transaction created.');
    }

    /**
     * Show the given transaction.
     */
    public function show(Request $request, Transaction $transaction): View
    {
        $this->authorizeOwnership($request, $transaction);

        return view('transactions.show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Show the form for editing the given transaction.
     */
    public function edit(Request $request, Transaction $transaction, CategoryService $categories): View
    {
        $this->authorizeOwnership($request, $transaction);

        return view('transactions.edit', [
            'transaction' => $transaction,
            'types' => TransactionType::cases(),
            'categories' => $categories->forUser($request->user()->id),
        ]);
    }

    /**
     * Update the given transaction.
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction, ConvertTransactionAmount $convert): RedirectResponse
    {
        $this->authorizeOwnership($request, $transaction);

        // Re-derive the base snapshot so an edited amount, currency, or date
        // cannot leave the converted total stale.
        $transaction->fill($convert->apply($request->validated()));

        $transaction->save();

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Transaction updated.');
    }

    /**
     * Remove the given transaction.
     */
    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeOwnership($request, $transaction);

        $transaction->delete();

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Transaction deleted.');
    }

    /**
     * Resolve the selected period into a from/to pair.
     *
     * A single day wins over a range, so the form can offer both without the
     * two contradicting each other. The bounds are the whole day rather than
     * midnight, otherwise everything entered after midnight is excluded.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function periodFrom(array $filters): array
    {
        if (! empty($filters['date'])) {
            $day = CarbonImmutable::createFromFormat('Y-m-d', $filters['date'])->startOfDay();

            return [$day, $day->endOfDay()];
        }

        $from = empty($filters['from']) ? null : CarbonImmutable::createFromFormat('Y-m-d', $filters['from'])->startOfDay();
        $to = empty($filters['to']) ? null : CarbonImmutable::createFromFormat('Y-m-d', $filters['to'])->endOfDay();

        return [$from, $to];
    }

    /**
     * The month the calendar should open on.
     *
     * An explicit request wins so the previous and next arrows can move away
     * from a selection. Otherwise the calendar follows the current selection,
     * so it never reopens on a month the chosen dates are not in.
     *
     * @param  array<string, mixed>  $filters
     */
    private function calendarMonth(array $filters): CarbonImmutable
    {
        if (! empty($filters['month'])) {
            return CarbonImmutable::createFromFormat('Y-m', $filters['month'])->startOfMonth();
        }

        foreach (['date', 'from', 'to'] as $key) {
            if (! empty($filters[$key])) {
                return CarbonImmutable::createFromFormat('Y-m-d', $filters[$key])->startOfMonth();
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * How many transactions fall on each day of the calendar month.
     *
     * The date filter itself is deliberately not applied, otherwise the dots
     * would only ever mark days already inside the selected range. The type
     * and category filters are applied so the marks match what is on screen.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<string, int>
     */
    private function activityByDay(Request $request, array $filters, CarbonImmutable $month): Collection
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        return $request->user()->transactions()
            ->when($filters['type'] ?? null, fn (Builder $q) => $q->where('type', $filters['type']))
            ->when($filters['category'] ?? null, fn (Builder $q) => $q->where('category', $filters['category']))
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('DATE(occurred_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
    }

    /**
     * Apply the type, category and date filters to a query.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, ?CarbonImmutable $from, ?CarbonImmutable $to): Builder
    {
        return $query
            ->when($filters['type'] ?? null, fn (Builder $q) => $q->where('type', $filters['type']))
            ->when($filters['category'] ?? null, fn (Builder $q) => $q->where('category', $filters['category']))
            ->when($from !== null, fn (Builder $q) => $q->where('occurred_at', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->where('occurred_at', '<=', $to));
    }

    /**
     * Build one group per day for the given dates.
     *
     * Totals come from the same base snapshot the summary cards use, so a day's
     * figure is directly comparable with the cards above it.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $dates
     * @return Collection<string, array<string, mixed>>
     */
    private function groupsFor(
        User $user,
        array $filters,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        array $dates,
    ): Collection {
        if ($dates === []) {
            return collect();
        }

        $transactions = $this->applyFilters($user->transactions()->getQuery(), $filters, $from, $to)
            ->where(function (Builder $query) use ($dates): void {
                foreach ($dates as $date) {
                    $start = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();

                    $query->orWhereBetween('occurred_at', [$start, $start->endOfDay()]);
                }
            })
            ->orderByDesc('occurred_at')
            ->get();

        return $transactions
            ->groupBy(fn (Transaction $transaction): string => $transaction->occurred_at->format('Y-m-d'))
            ->map(function (Collection $day, string $date): array {
                $income = (float) $day->where('type', TransactionType::Income)->sum('base_amount');
                $expenses = (float) $day->where('type', TransactionType::Expense)->sum('base_amount');

                return [
                    'date' => $date,
                    'label' => CarbonImmutable::createFromFormat('Y-m-d', $date)->format('D j M Y'),
                    'count' => $day->count(),
                    'income' => round($income, 2),
                    'expenses' => round($expenses, 2),
                    'net' => round($income - $expenses, 2),
                    'transactions' => $day->values(),
                ];
            });
    }

    /**
     * Ensure the transaction belongs to the authenticated user.
     */
    private function authorizeOwnership(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
    }
}
