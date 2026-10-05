<?php

namespace App\Http\Controllers;

use App\Actions\ConvertTransactionAmount;
use App\Enums\TransactionType;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    /**
     * Display a listing of the authenticated user's transactions.
     */
    public function index(Request $request): View
    {
        $transactions = $request->user()
            ->transactions()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->latest('occurred_at')
            ->paginate(15)
            ->withQueryString();

        // Totals are summed from the frozen base snapshot rather than the
        // original amount, so mixed currencies never add up incorrectly.
        $summary = $request->user()->transactions()
            ->whereNotNull('base_amount')
            ->selectRaw('type, SUM(base_amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        // Rounded to the precision the base snapshot is stored at, so totals do
        // not display float noise such as 16025.940000000002.
        $income = round((float) ($summary[TransactionType::Income->value] ?? 0), 4);
        $expenses = round((float) ($summary[TransactionType::Expense->value] ?? 0), 4);

        return view('transactions.index', [
            'transactions' => $transactions,
            'income' => $income,
            'expenses' => $expenses,
            'balance' => round($income - $expenses, 4),
            'baseCurrency' => config('finance.base_currency'),
            'unconvertedCount' => $request->user()->transactions()->whereNull('base_amount')->count(),
            'types' => TransactionType::cases(),
        ]);
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create(): View
    {
        return view('transactions.create', [
            'transaction' => new Transaction(['currency' => 'CNY', 'type' => TransactionType::Expense]),
            'types' => TransactionType::cases(),
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
    public function edit(Request $request, Transaction $transaction): View
    {
        $this->authorizeOwnership($request, $transaction);

        return view('transactions.edit', [
            'transaction' => $transaction,
            'types' => TransactionType::cases(),
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
     * Ensure the transaction belongs to the authenticated user.
     */
    private function authorizeOwnership(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
    }
}
