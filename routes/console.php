<?php

use App\Console\Commands\GenerateRecurringTransactions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Recurring occurrences are generated daily. withoutOverlapping is not
| cosmetic: a run that takes longer than a day, or a second server, would
| otherwise post the same occurrence twice. The unique index on
| (recurring_transaction_id, occurrence_on) is the backstop, but not having two
| runs compete in the first place is cheaper than catching the violation.
|
*/

Schedule::command(GenerateRecurringTransactions::class)
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
