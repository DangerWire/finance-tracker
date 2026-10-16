<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RecurringTransactionRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('finance:generate-recurring
    {--date= : Treat this date as today, for testing or catching up manually}
    {--user= : Limit to one user id}
    {--dry-run : Report what would happen without writing anything}
    {--no-reminders : Post occurrences but send no reminder emails}')]
#[Description('Create transactions for due recurring occurrences and send bill reminders')]
class GenerateRecurringTransactions extends Command
{
    public function handle(RecurringTransactionRunner $runner): int
    {
        $through = $this->dateOption();
        $dryRun = (bool) $this->option('dry-run');

        $users = $this->option('user')
            ? User::query()->whereKey($this->option('user'))->get()
            : User::query()->has('recurringTransactions')->get();

        if ($users->isEmpty()) {
            $this->components->info('No recurring templates found.');

            return self::SUCCESS;
        }

        $posted = $skipped = $reminded = 0;
        $capped = [];
        $failures = [];

        foreach ($users as $user) {
            $result = $runner->run($user, $through, $dryRun);

            $posted += $result['posted'];
            $skipped += $result['skipped'];
            $capped = array_merge($capped, $result['capped']);

            foreach ($result['skipped_details'] as $detail) {
                if ($detail['reason'] === 'failed') {
                    $failures[] = $detail['name'];
                }
            }

            if (! $this->option('no-reminders')) {
                $reminded += count($runner->sendReminders($user, $through, $dryRun));
            }
        }

        $this->components->twoColumnDetail('Date', $through->toDateString());
        $this->components->twoColumnDetail('Users processed', (string) $users->count());
        $this->components->twoColumnDetail(
            $dryRun ? 'Would post' : 'Posted',
            (string) $posted,
        );
        $this->components->twoColumnDetail('Skipped', (string) $skipped);
        $this->components->twoColumnDetail('Reminders sent', (string) $reminded);

        if ($capped !== []) {
            // Silent truncation would make the totals quietly wrong, which is
            // the failure mode this project has already been bitten by.
            $this->components->warn(
                count($capped).' template(s) had more missed occurrences than the catch-up limit; older occurrences were dropped. Run again to work through the rest.'
            );
        }

        if ($failures !== []) {
            $this->components->error('Could not post: '.implode(', ', array_unique($failures)));
        }

        if ($dryRun) {
            $this->newLine();
            $this->components->info('Dry run: nothing was written.');
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    private function dateOption(): CarbonImmutable
    {
        $date = $this->option('date');

        if ($date === null) {
            return CarbonImmutable::now()->startOfDay();
        }

        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date);

        if ($parsed === false) {
            $this->components->error('--date must look like YYYY-MM-DD.');

            exit(self::INVALID);
        }

        return $parsed->startOfDay();
    }
}
