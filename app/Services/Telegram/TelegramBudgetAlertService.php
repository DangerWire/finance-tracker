<?php

namespace App\Services\Telegram;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\SpendingStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBudgetAlertService
{
    public function __construct(
        private readonly TelegramBotService $bot,
        private readonly SpendingStatsService $stats,
    ) {}

    /**
     * Check if recording an expense pushed any category budget to 80%+ or 100%+ and alert via Telegram.
     */
    public function checkForTransaction(Transaction $transaction): void
    {
        try {
            if ($transaction->type !== TransactionType::Expense || $transaction->occurred_at === null) {
                return;
            }

            $user = $transaction->user;
            if (! $user || ! $user->isTelegramLinked()) {
                return;
            }

            $transaction->loadMissing('splits');

            $rawCategories = $transaction->splits->isNotEmpty()
                ? $transaction->splits->pluck('category')->all()
                : [$transaction->category];

            $affectedCategories = [];
            foreach ($rawCategories as $cat) {
                $trimmed = trim((string) $cat);
                if ($trimmed !== '') {
                    $affectedCategories[mb_strtolower($trimmed)] = true;
                }
            }

            if ($affectedCategories === []) {
                return;
            }

            $occurredAt = CarbonImmutable::instance($transaction->occurred_at);
            $overview = $this->stats->budgetOverview($user->id, $occurredAt);
            $currency = $overview['currency'];

            foreach ($overview['items'] as $item) {
                $catKey = mb_strtolower(trim((string) $item['category']));
                if (! isset($affectedCategories[$catKey])) {
                    continue;
                }

                $pct = (float) $item['percentage_used'];
                $level = match (true) {
                    $pct >= 100.0 => 'exceeded',
                    $pct >= 80.0 => 'warning',
                    default => null,
                };

                if ($level === null) {
                    continue;
                }

                $cacheKey = "telegram_budget_alert:{$user->id}:{$item['id']}:{$overview['month']}:{$level}";
                if (! Cache::add($cacheKey, true, $occurredAt->endOfMonth()->addDay())) {
                    continue;
                }

                $headline = $level === 'exceeded'
                    ? '🚨 <b>Budget Exceeded Alert!</b>'
                    : '⚠️ <b>Budget Warning Alert</b>';

                $spentFmt = number_format((float) $item['spent_amount'], 2);
                $budgetFmt = number_format((float) $item['budget_amount'], 2);
                $pctFmt = number_format($pct, 1);
                $remaining = (float) $item['remaining_amount'];

                $balanceLine = $remaining >= 0
                    ? 'Remaining: <b>'.number_format($remaining, 2)." {$currency}</b>"
                    : 'Over budget by: <b>'.number_format(abs($remaining), 2)." {$currency}</b>";

                $message = implode("\n", [
                    $headline,
                    '',
                    'Category: <b>'.e($item['category']).'</b>',
                    "Month: {$overview['month']}",
                    "Spent: <b>{$spentFmt} / {$budgetFmt} {$currency}</b> ({$pctFmt}%)",
                    $balanceLine,
                ]);

                $this->bot->sendMessage((int) $user->telegram_chat_id, $message);
            }
        } catch (Throwable $e) {
            Log::warning('TelegramBudgetAlertService: failed to check/send alert', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
