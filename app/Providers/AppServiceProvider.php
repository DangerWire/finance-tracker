<?php

namespace App\Providers;

use App\Models\Budget;
use App\Models\Debt;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use App\Models\User;
use App\Services\UserDataCache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerUserDataCacheInvalidation();
    }

    /**
     * Automatically invalidate per-user cached aggregates whenever financial
     * records change via Eloquent.
     */
    private function registerUserDataCacheInvalidation(): void
    {
        $bump = static function (?int $userId): void {
            app(UserDataCache::class)->bump($userId);
        };

        Transaction::saved(fn (Transaction $transaction) => $bump($transaction->user_id));
        Transaction::deleted(fn (Transaction $transaction) => $bump($transaction->user_id));

        $bumpSplitOwner = static function (TransactionSplit $split) use ($bump): void {
            $ownerId = $split->relationLoaded('transaction')
                ? $split->transaction?->user_id
                : Transaction::whereKey($split->transaction_id)->value('user_id');

            $bump($ownerId !== null ? (int) $ownerId : null);
        };

        TransactionSplit::saved($bumpSplitOwner);
        TransactionSplit::deleted($bumpSplitOwner);

        Budget::saved(fn (Budget $budget) => $bump($budget->user_id));
        Budget::deleted(fn (Budget $budget) => $bump($budget->user_id));

        RecurringTransaction::saved(fn (RecurringTransaction $recurring) => $bump($recurring->user_id));
        RecurringTransaction::deleted(fn (RecurringTransaction $recurring) => $bump($recurring->user_id));

        $bumpDebtParticipants = static function (Debt $debt) use ($bump): void {
            foreach (array_unique(array_filter([$debt->lender_id, $debt->borrower_id, $debt->creator_id])) as $participantId) {
                $bump((int) $participantId);
            }
        };

        Debt::saved($bumpDebtParticipants);
        Debt::deleted($bumpDebtParticipants);

        User::updated(static function (User $user) use ($bump): void {
            if ($user->wasChanged(['currency', 'prefers_base_currency'])) {
                $bump($user->id);
            }
        });
    }
}
