<?php

namespace App\Http\Controllers;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Debt;
use App\Services\SpendingStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Render the personal finance command-center dashboard.
     */
    public function index(Request $request, SpendingStatsService $stats): View
    {
        $user = $request->user();
        $baseCurrency = strtoupper((string) ($user->currency ?? config('finance.base_currency', 'CNY')));
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();

        // Overall all-time totals strictly summed from base_amount in the active base currency.
        $totalSummary = $user->transactions()
            ->whereNotNull('base_amount')
            ->where('base_currency', $baseCurrency)
            ->selectRaw('type, SUM(base_amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $totalIncome = round((float) ($totalSummary[TransactionType::Income->value] ?? 0), 4);
        $totalExpenses = round((float) ($totalSummary[TransactionType::Expense->value] ?? 0), 4);
        $totalBalance = round($totalIncome - $totalExpenses, 4);

        // Current month cashflow bounded cleanly to startOfMonth..endOfMonth.
        $monthlySummary = $user->transactions()
            ->whereBetween('occurred_at', [$monthStart, $monthEnd])
            ->whereNotNull('base_amount')
            ->where('base_currency', $baseCurrency)
            ->selectRaw('type, SUM(base_amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $monthlyIncome = round((float) ($monthlySummary[TransactionType::Income->value] ?? 0), 4);
        $monthlyExpenses = round((float) ($monthlySummary[TransactionType::Expense->value] ?? 0), 4);
        $monthlyNet = round($monthlyIncome - $monthlyExpenses, 4);
        $savingsRate = $monthlyIncome > 0.0
            ? round((($monthlyIncome - $monthlyExpenses) / $monthlyIncome) * 100, 1)
            : null;

        // Excluded rows that cannot be aggregated into the active base currency.
        $unconvertedCount = $user->transactions()
            ->where(fn ($q) => $q
                ->whereNull('base_amount')
                ->orWhere('base_currency', '!=', $baseCurrency))
            ->count();

        $recentTransactions = $user->transactions()
            ->with('splits')
            ->latest('occurred_at')
            ->latest('id')
            ->take(7)
            ->get();

        $spendingStats = $stats->forUser($user->id, $now);
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
            ->get();

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

        return view('dashboard', [
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
        ]);
    }
}
