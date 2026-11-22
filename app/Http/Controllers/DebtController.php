<?php

namespace App\Http\Controllers;

use App\Actions\SettleDebtAction;
use App\Enums\DebtStatus;
use App\Http\Requests\StoreDebtRequest;
use App\Models\Debt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DebtController extends Controller
{
    /**
     * Display a listing of user debts, summaries, and history.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $baseCurrency = strtoupper((string) ($user->currency ?? config('finance.base_currency', 'CNY')));

        $allUserDebts = Debt::query()
            ->where(function ($q) use ($user) {
                $q->where('lender_id', $user->id)
                    ->orWhere('borrower_id', $user->id);
            })
            ->with(['creator', 'lender', 'borrower'])
            ->latest()
            ->get();

        $pendingDebts = $allUserDebts->where('status', DebtStatus::PendingAcceptance)->values();
        $activeDebts = $allUserDebts->where('status', DebtStatus::Active)->values();
        $awaitingConfirmationDebts = $allUserDebts->where('status', DebtStatus::PaymentSubmitted)->values();
        $settledDebts = $allUserDebts->where('status', DebtStatus::Settled)->values();

        $outstandingDebts = $allUserDebts->filter(
            fn (Debt $d) => in_array($d->status, [DebtStatus::Active, DebtStatus::PaymentSubmitted])
        );

        $totalLent = (float) $outstandingDebts->where('lender_id', $user->id)->sum('amount');
        $totalBorrowed = (float) $outstandingDebts->where('borrower_id', $user->id)->sum('amount');
        $netPosition = $totalLent - $totalBorrowed;

        $friends = $user->allFriends();

        return view('debts.index', [
            'totalLent' => $totalLent,
            'totalBorrowed' => $totalBorrowed,
            'netPosition' => $netPosition,
            'pendingDebts' => $pendingDebts,
            'activeDebts' => $activeDebts,
            'awaitingConfirmationDebts' => $awaitingConfirmationDebts,
            'settledDebts' => $settledDebts,
            'friends' => $friends,
            'baseCurrency' => $baseCurrency,
        ]);
    }

    /**
     * Store a newly created peer-to-peer loan proposal.
     */
    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $friendId = (int) $validated['friend_id'];

        $isLent = $validated['type'] === 'lent';

        Debt::create([
            'creator_id' => $user->id,
            'lender_id' => $isLent ? $user->id : $friendId,
            'borrower_id' => $isLent ? $friendId : $user->id,
            'amount' => $validated['amount'],
            'currency' => strtoupper((string) $validated['currency']),
            'note' => $validated['note'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => DebtStatus::PendingAcceptance,
        ]);

        return redirect()
            ->route('debts.index')
            ->with('status', __('Loan proposal created and awaiting acceptance.'));
    }

    /**
     * Accept a pending loan agreement proposed by the counterparty.
     */
    public function accept(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->isParticipant($request->user()), 403);
        abort_if($debt->creator_id === $request->user()->id, 403);
        abort_unless($debt->status === DebtStatus::PendingAcceptance, 400);

        $debt->update([
            'status' => DebtStatus::Active,
            'accepted_at' => now(),
        ]);

        return redirect()
            ->route('debts.index')
            ->with('status', __('Loan proposal accepted.'));
    }

    /**
     * Decline a proposed loan agreement.
     */
    public function decline(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->isParticipant($request->user()), 403);
        abort_unless($debt->status === DebtStatus::PendingAcceptance, 400);

        $debt->update([
            'status' => DebtStatus::Declined,
        ]);

        return redirect()
            ->route('debts.index')
            ->with('status', __('Loan proposal declined.'));
    }

    /**
     * Borrower marks the active loan as paid.
     */
    public function pay(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->borrower_id === $request->user()->id, 403);
        abort_unless($debt->status === DebtStatus::Active, 400);

        $debt->update([
            'status' => DebtStatus::PaymentSubmitted,
            'payment_submitted_at' => now(),
        ]);

        return redirect()
            ->route('debts.index')
            ->with('status', __('Payment marked as submitted. Awaiting confirmation from lender.'));
    }

    /**
     * Lender confirms payment receipt, triggering dual-ledger settlement.
     */
    public function confirm(Request $request, Debt $debt, SettleDebtAction $settleAction): RedirectResponse
    {
        abort_unless($debt->lender_id === $request->user()->id, 403);
        abort_unless($debt->status === DebtStatus::PaymentSubmitted, 400);

        $settleAction->execute($debt);

        return redirect()
            ->route('debts.index')
            ->with('status', __('Payment confirmed and loan settled.'));
    }

    /**
     * Lender disputes the submitted payment as not received.
     */
    public function dispute(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->lender_id === $request->user()->id, 403);
        abort_unless($debt->status === DebtStatus::PaymentSubmitted, 400);

        $debt->update([
            'status' => DebtStatus::Active,
            'payment_submitted_at' => null,
        ]);

        return redirect()
            ->route('debts.index')
            ->with('status', __('Payment disputed. Loan remains active.'));
    }
}
