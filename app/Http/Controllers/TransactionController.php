<?php

namespace App\Http\Controllers;

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

        $summary = $request->user()->transactions()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = (float) ($summary[TransactionType::Income->value] ?? 0);
        $expenses = (float) ($summary[TransactionType::Expense->value] ?? 0);

        return view('transactions.index', [
            'transactions' => $transactions,
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $income - $expenses,
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
    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $request->user()->transactions()->create($request->validated());

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
    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeOwnership($request, $transaction);

        $transaction->update($request->validated());

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
