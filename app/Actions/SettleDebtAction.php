<?php

namespace App\Actions;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Debt;
use DomainException;
use Illuminate\Support\Facades\DB;

class SettleDebtAction
{
    public function __construct(
        private readonly ConvertTransactionAmount $convert
    ) {}

    /**
     * Settle a debt in full and create synchronized ledger transactions for both parties.
     *
     * @throws DomainException
     */
    public function execute(Debt $debt): Debt
    {
        return DB::transaction(function () use ($debt) {
            /** @var Debt $lockedDebt */
            $lockedDebt = Debt::query()
                ->where('id', $debt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDebt->status !== DebtStatus::PaymentSubmitted) {
                throw new DomainException('Debt is not awaiting settlement.');
            }

            $lender = $lockedDebt->lender;
            $borrower = $lockedDebt->borrower;

            // 1. Post + Income to Lender
            $lenderTxData = [
                'occurred_at' => now(),
                'type' => TransactionType::Income,
                'amount' => $lockedDebt->amount,
                'currency' => $lockedDebt->currency,
                'category' => 'Debt Repayment',
                'note' => __('Repayment from :name: :note', [
                    'name' => $borrower->name,
                    'note' => $lockedDebt->note ?? '',
                ]),
            ];
            $lenderTxData = $this->convert->apply($lenderTxData, $lender->currency);
            $lenderTx = $lender->transactions()->create($lenderTxData);

            // 2. Post - Expense to Borrower
            $borrowerTxData = [
                'occurred_at' => now(),
                'type' => TransactionType::Expense,
                'amount' => $lockedDebt->amount,
                'currency' => $lockedDebt->currency,
                'category' => 'Debt Settlement',
                'note' => __('Repaid to :name: :note', [
                    'name' => $lender->name,
                    'note' => $lockedDebt->note ?? '',
                ]),
            ];
            $borrowerTxData = $this->convert->apply($borrowerTxData, $borrower->currency);
            $borrowerTx = $borrower->transactions()->create($borrowerTxData);

            // 3. Update Debt status and transaction links
            $lockedDebt->update([
                'status' => DebtStatus::Settled,
                'settled_at' => now(),
                'lender_transaction_id' => $lenderTx->id,
                'borrower_transaction_id' => $borrowerTx->id,
            ]);

            return $lockedDebt;
        });
    }
}
