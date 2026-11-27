<?php

namespace App\Http\Controllers;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Debt;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\SpendingStatsService;
use App\Services\UserDataCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Render the personal finance command-center dashboard.
     */
    public function index(Request $request, SpendingStatsService $stats, UserDataCache $cache): View
    {
        $user = $request->user();
        $baseCurrency = strtoupper((string) ($user->currency ?? config('finance.base_currency', 'CNY')));
        $now = CarbonImmutable::now();

        $data = $cache->remember(
            $user->id,
            "dashboard:{$now->toDateString()}:{$baseCurrency}",
            function () use ($user, $baseCurrency, $now, $stats): array {
                $monthStart = $now->startOfMonth();
                $monthEnd = $now->endOfMonth();

                // Combine all-time totals, current-month cashflow, and unconverted row count
                // into one SQL aggregate query to minimize remote DB round-trips.
                $aggregates = $user->transactions()
                    ->selectRaw(
                        'COALESCE(SUM(CASE WHEN base_amount IS NOT NULL AND base_currency = ? AND type = ? THEN base_amount ELSE 0 END), 0) as total_income, '.
                        'COALESCE(SUM(CASE WHEN base_amount IS NOT NULL AND base_currency = ? AND type = ? THEN base_amount ELSE 0 END), 0) as total_expenses, '.
                        'COALESCE(SUM(CASE WHEN base_amount IS NOT NULL AND base_currency = ? AND type = ? AND occurred_at BETWEEN ? AND ? THEN base_amount ELSE 0 END), 0) as monthly_income, '.
                        'COALESCE(SUM(CASE WHEN base_amount IS NOT NULL AND base_currency = ? AND type = ? AND occurred_at BETWEEN ? AND ? THEN base_amount ELSE 0 END), 0) as monthly_expenses, '.
                        'COALESCE(SUM(CASE WHEN base_amount IS NULL OR base_currency != ? THEN 1 ELSE 0 END), 0) as unconverted_count',
                        [
                            $baseCurrency, TransactionType::Income->value,
                            $baseCurrency, TransactionType::Expense->value,
                            $baseCurrency, TransactionType::Income->value, $monthStart->toDateTimeString(), $monthEnd->toDateTimeString(),
                            $baseCurrency, TransactionType::Expense->value, $monthStart->toDateTimeString(), $monthEnd->toDateTimeString(),
                            $baseCurrency,
                        ]
                    )
                    ->first();

                $totalIncome = round((float) ($aggregates?->total_income ?? 0), 4);
                $totalExpenses = round((float) ($aggregates?->total_expenses ?? 0), 4);
                $totalBalance = round($totalIncome - $totalExpenses, 4);

                $monthlyIncome = round((float) ($aggregates?->monthly_income ?? 0), 4);
                $monthlyExpenses = round((float) ($aggregates?->monthly_expenses ?? 0), 4);
                $monthlyNet = round($monthlyIncome - $monthlyExpenses, 4);
                $savingsRate = $monthlyIncome > 0.0
                    ? round((($monthlyIncome - $monthlyExpenses) / $monthlyIncome) * 100, 1)
                    : null;

                $unconvertedCount = (int) ($aggregates?->unconverted_count ?? 0);

                $recentTransactions = $user->transactions()
                    ->with('splits')
                    ->latest('occurred_at')
                    ->latest('id')
                    ->take(7)
                    ->get()
                    ->map(fn (Transaction $transaction): array => [
                        'id' => (int) $transaction->id,
                        'type' => $transaction->type->value,
                        'type_label' => $transaction->type->label(),
                        'sign' => $transaction->type->sign(),
                        'is_split' => $transaction->isSplit(),
                        'splits_count' => $transaction->splits->count(),
                        'category' => $transaction->category,
                        'occurred_at_formatted' => $transaction->occurred_at?->format('M d · H:i') ?? '',
                        'note' => $transaction->note,
                        'amount' => (float) $transaction->amount,
                        'currency' => (string) $transaction->currency,
                        'base_amount' => $transaction->base_amount !== null ? (float) $transaction->base_amount : null,
                        'base_currency' => $transaction->base_currency !== null ? (string) $transaction->base_currency : null,
                    ])
                    ->values()
                    ->all();

                $spendingStats = $stats->forUser($user->id, $now, $user);
                $budgetOverview = $spendingStats['budgets'];

                $budgetItems = $budgetOverview['items'];
                usort($budgetItems, fn (array $a, array $b): int => $b['percentage_used'] <=> $a['percentage_used']);
                $topBudgets = array_slice($budgetItems, 0, 4);

                $topCategories = array_slice($spendingStats['categories'], 0, 5);
                $spendingChangePercent = $spendingStats['previous_period']['change_percent'];
                $previousMonthLabel = $spendingStats['previous_period']['label'];

                $upcomingRecurring = $user->recurringTransactions()
                    ->where('is_active', true)
                    ->whereNotNull('next_occurrence_on')
                    ->orderBy('next_occurrence_on')
                    ->take(4)
                    ->get()
                    ->map(fn (RecurringTransaction $item): array => [
                        'name' => $item->name,
                        'category' => $item->category,
                        'next_occurrence_formatted' => $item->next_occurrence_on?->format('M d') ?? '',
                        'frequency_label' => $item->frequency->label(),
                        'type' => $item->type->value,
                        'sign' => $item->type->sign(),
                        'amount' => (float) $item->amount,
                        'currency' => (string) $item->currency,
                    ])
                    ->values()
                    ->all();

                $allDebts = Debt::query()
                    ->where(fn ($q) => $q
                        ->where('lender_id', $user->id)
                        ->orWhere('borrower_id', $user->id))
                    ->get();

                $outstandingDebts = $allDebts->filter(
                    fn (Debt $debt): bool => in_array($debt->status, [DebtStatus::Active, DebtStatus::PaymentSubmitted], true)
                );

                $totalLent = round((float) $outstandingDebts->where('lender_id', $user->id)->sum('amount'), 2);
                $totalBorrowed = round((float) $outstandingDebts->where('borrower_id', $user->id)->sum('amount'), 2);
                $netDebtPosition = round($totalLent - $totalBorrowed, 2);

                $pendingDebtActionsCount = $allDebts->filter(
                    fn (Debt $debt): bool => ($debt->status === DebtStatus::PendingAcceptance && $debt->creator_id !== $user->id)
                        || ($debt->status === DebtStatus::PaymentSubmitted && $debt->lender_id === $user->id)
                )->count();

                return [
                    'monthLabel' => $now->format('F Y'),
                    'baseCurrency' => $baseCurrency,
                    'totalIncome' => $totalIncome,
                    'totalExpenses' => $totalExpenses,
                    'totalBalance' => $totalBalance,
                    'monthlyIncome' => $monthlyIncome,
                    'monthlyExpenses' => $monthlyExpenses,
                    'monthlyNet' => $monthlyNet,
                    'savingsRate' => $savingsRate,
                    'unconvertedCount' => $unconvertedCount,
                    'recentTransactions' => $recentTransactions,
                    'budgetOverview' => $budgetOverview,
                    'topBudgets' => $topBudgets,
                    'topCategories' => $topCategories,
                    'spendingChangePercent' => $spendingChangePercent,
                    'previousMonthLabel' => $previousMonthLabel,
                    'upcomingRecurring' => $upcomingRecurring,
                    'totalLent' => $totalLent,
                    'totalBorrowed' => $totalBorrowed,
                    'netDebtPosition' => $netDebtPosition,
                    'pendingDebtActionsCount' => $pendingDebtActionsCount,
                ];
            }
        );

        return view('dashboard', $data);
    }
}
