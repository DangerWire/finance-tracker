<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use App\Services\CategoryService;
use App\Services\SpendingStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    /**
     * Display a listing of monthly category budgets and progress.
     */
    public function index(Request $request, SpendingStatsService $stats, CategoryService $categories): View
    {
        $rawMonth = (string) $request->input('month', now()->format('Y-m'));
        $monthDate = $this->parseMonth($rawMonth) ?? CarbonImmutable::now()->startOfMonth();
        $month = $monthDate->format('Y-m');

        $overview = $stats->budgetOverview($request->user()->id, $monthDate);
        $userCategories = $categories->forUser($request->user()->id);

        return view('budgets.index', [
            'overview' => $overview,
            'currentMonth' => $month,
            'monthLabel' => $monthDate->format('F Y'),
            'prevMonth' => $monthDate->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $monthDate->addMonthNoOverflow()->format('Y-m'),
            'categories' => $userCategories,
            'baseCurrency' => strtoupper((string) config('finance.base_currency')),
        ]);
    }

    /**
     * Store a newly created budget.
     */
    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $request->user()->budgets()->create($request->validated());

        return redirect()
            ->route('budgets.index', ['month' => $request->input('period_month')])
            ->with('status', 'Budget created successfully.');
    }

    /**
     * Update the specified budget.
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorizeOwnership($request, $budget);

        $budget->update($request->validated());

        return redirect()
            ->route('budgets.index', ['month' => $budget->period_month])
            ->with('status', 'Budget updated successfully.');
    }

    /**
     * Remove the specified budget.
     */
    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorizeOwnership($request, $budget);

        $periodMonth = $budget->period_month;
        $budget->delete();

        return redirect()
            ->route('budgets.index', ['month' => $periodMonth])
            ->with('status', 'Budget deleted successfully.');
    }

    /**
     * Ensure the budget belongs to the authenticated user.
     */
    private function authorizeOwnership(Request $request, Budget $budget): void
    {
        abort_unless($budget->user_id === $request->user()->id, 403);
    }

    /**
     * Parse month string or return null if invalid.
     */
    private function parseMonth(string $value): ?CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m', $value);

            return $parsed === false ? null : $parsed->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }
}
