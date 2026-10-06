<?php

namespace App\Console\Commands;

use App\Actions\ConvertTransactionAmount;
use App\Models\Transaction;
use Illuminate\Console\Command;

/**
 * Populates the base currency snapshot for transactions that need one.
 *
 * Two kinds of row qualify: transactions that predate the snapshot, and
 * transactions whose snapshot was taken into a base currency other than the
 * one now configured. The second case is why this is not simply a check for a
 * missing base amount: those rows carry a figure that is numerically present
 * but denominated in the wrong currency, so totals silently blend two
 * currencies until they are recomputed.
 *
 * Safe to re-run: transactions already converted into the current base
 * currency are skipped.
 */
class BackfillTransactionBaseAmounts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finance:backfill-base-amounts {--force : Convert every transaction, including ones already in the current base currency}';

    /**
     * @var string
     */
    protected $description = 'Convert existing transactions into the base currency';

    public function handle(ConvertTransactionAmount $convert): int
    {
        $baseCurrency = strtoupper((string) config('finance.base_currency'));

        $query = Transaction::query();

        if ($this->option('force')) {
            $query->whereRaw('1 = 1');
        } else {
            $query->where(function ($query) use ($baseCurrency): void {
                $query->whereNull('base_amount')
                    ->orWhere('base_currency', '!=', $baseCurrency);
            });
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->components->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->components->info("Converting {$total} transactions into {$baseCurrency}.");

        $converted = 0;
        $skipped = 0;

        $query->chunkById(50, function ($transactions) use ($convert, &$converted, &$skipped): void {
            foreach ($transactions as $transaction) {
                $convert->recalculate($transaction);

                if ($transaction->base_amount === null) {
                    $skipped++;

                    continue;
                }

                $transaction->save();
                $converted++;
            }

            $this->components->twoColumnDetail('Converted', (string) $converted);
        });

        $this->newLine();

        if ($skipped > 0) {
            $this->components->warn("{$skipped} transaction(s) had no available rate and remain unconverted.");
        }

        $this->components->info("Done. {$converted} converted, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
