<?php

namespace App\Models;

use App\Enums\DebtStatus;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'creator_id',
    'lender_id',
    'borrower_id',
    'amount',
    'currency',
    'note',
    'due_date',
    'status',
    'accepted_at',
    'payment_submitted_at',
    'settled_at',
    'lender_transaction_id',
    'borrower_transaction_id',
])]
class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'due_date' => 'date',
            'status' => DebtStatus::class,
            'accepted_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * The user who initiated/created this debt record.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * The lender (creditor) who lent the money.
     *
     * @return BelongsTo<User, $this>
     */
    public function lender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lender_id');
    }

    /**
     * The borrower (debtor) who owes the money.
     *
     * @return BelongsTo<User, $this>
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    /**
     * The ledger transaction recorded for the lender upon settlement.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function lenderTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'lender_transaction_id');
    }

    /**
     * The ledger transaction recorded for the borrower upon settlement.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function borrowerTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'borrower_transaction_id');
    }

    /**
     * Check if the given user is a participant (either lender or borrower) in this debt.
     */
    public function isParticipant(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->lender_id === $userId || $this->borrower_id === $userId;
    }

    /**
     * Resolve the counterparty relative to the given or current user.
     */
    public function counterparty(?User $currentUser = null): ?User
    {
        $currentUser ??= auth()->user();

        if (! $currentUser) {
            return $this->borrower ?? $this->lender;
        }

        return $this->lender_id === $currentUser->id ? $this->borrower : $this->lender;
    }
}
