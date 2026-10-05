<?php

namespace App\Console\Commands;

use App\Actions\ConvertTransactionAmount;
use App\Models\Transaction;
use Illuminate\Console\Command;

/**
 * Populates the base currency snapshot for transactions that predate it.
 *
 * Safe to re-run: transactions that already carry a base amount are skipped.
 */
class BackfillTransactionBaseAmounts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finance:backfill-base-amounts {--force : Convert even transactions that already have a base amount}';

    /**
     * @var string
     */
    protected $description = 'Convert existing transactions into the base currency';

    public function handle(ConvertTransactionAmount $convert): int
    {
        $baseCurrency = config('finance.base_currency');

        $query = Transaction::query()->whereNull('base_amount');

        if (! $this->option('force')) {
            $query->whereNull('base_currency');
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
