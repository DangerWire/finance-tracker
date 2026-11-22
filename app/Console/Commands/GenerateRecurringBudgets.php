<?php

namespace App\Console\Commands;

use App\Services\RecurringBudgetService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class GenerateRecurringBudgets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:generate-recurring-budgets {--month= : Target month in YYYY-MM format (defaults to current month)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Carry forward active recurring monthly budgets into the target month';

    /**
     * Execute the console command.
     */
    public function handle(RecurringBudgetService $service): int
    {
        $rawMonth = $this->option('month');
        $targetMonth = $rawMonth !== null && $rawMonth !== ''
            ? trim((string) $rawMonth)
            : CarbonImmutable::now()->format('Y-m');

        if (! $this->isValidMonth($targetMonth)) {
            $this->error("Invalid month '{$targetMonth}'. Expected YYYY-MM format (e.g. 2026-12).");

            return self::FAILURE;
        }

        $created = $service->ensureForAllUsersMonth($targetMonth);

        $this->info("Generated {$created} recurring budget(s) for {$targetMonth}.");

        return self::SUCCESS;
    }

    private function isValidMonth(string $value): bool
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m', $value);

            return $parsed !== false && $parsed->format('Y-m') === $value;
        } catch (Throwable) {
            return false;
        }
    }
}
