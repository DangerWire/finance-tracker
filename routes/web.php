<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\SpendingInsightController;
use App\Http\Controllers\TelegramLinkController;
use App\Http\Controllers\TelegramMiniAppAuthController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionExportController;
use App\Http\Controllers\TransactionImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::put('/locale/{locale}', [LocaleController::class, 'update'])
    ->whereIn('locale', ['en', 'id'])
    ->name('locale.update');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Registered before the resource so that the literal /transactions/import
    // paths are not captured by the /transactions/{transaction} wildcard.
    Route::get('/transactions/import', [TransactionImportController::class, 'create'])
        ->name('transaction-imports.create');
    Route::get('/transactions/imports', [TransactionImportController::class, 'index'])
        ->name('transaction-imports.index');
    Route::post('/transactions/import/preview', [TransactionImportController::class, 'preview'])
        ->name('transaction-imports.preview');
    Route::post('/transactions/import/remap', [TransactionImportController::class, 'remap'])
        ->name('transaction-imports.remap');
    Route::post('/transactions/import', [TransactionImportController::class, 'store'])
        ->name('transaction-imports.store');
    Route::delete('/transactions/imports/{import}', [TransactionImportController::class, 'destroy'])
        ->name('transaction-imports.destroy');

    Route::get('/transactions/export/csv', [TransactionExportController::class, 'csv'])
        ->name('transactions.export.csv');
    Route::get('/transactions/export/statement', [TransactionExportController::class, 'statement'])
        ->name('transactions.export.statement');

    // Serves the calendar fragment for month navigation. Registered before the
    // resource, or /transactions/{transaction} captures it as an id and the
    // lookup 404s.
    Route::get('/transactions/calendar', [TransactionController::class, 'calendar'])
        ->name('transactions.calendar');

    Route::resource('transactions', TransactionController::class);

    Route::post('recurring/run', [RecurringTransactionController::class, 'run'])
        ->name('recurring.run');
    Route::resource('recurring', RecurringTransactionController::class);

    Route::resource('budgets', BudgetController::class);

    Route::get('/friends', [FriendshipController::class, 'index'])->name('friends.index');
    Route::post('/friends', [FriendshipController::class, 'store'])->name('friends.store');
    Route::patch('/friends/{friendship}', [FriendshipController::class, 'update'])->name('friends.update');
    Route::delete('/friends/{friendship}', [FriendshipController::class, 'destroy'])->name('friends.destroy');

    Route::get('/debts', [DebtController::class, 'index'])->name('debts.index');
    Route::post('/debts', [DebtController::class, 'store'])->name('debts.store');
    Route::post('/debts/{debt}/accept', [DebtController::class, 'accept'])->name('debts.accept');
    Route::post('/debts/{debt}/decline', [DebtController::class, 'decline'])->name('debts.decline');
    Route::post('/debts/{debt}/pay', [DebtController::class, 'pay'])->name('debts.pay');
    Route::post('/debts/{debt}/confirm', [DebtController::class, 'confirm'])->name('debts.confirm');
    Route::post('/debts/{debt}/dispute', [DebtController::class, 'dispute'])->name('debts.dispute');

    Route::get('/insights', [SpendingInsightController::class, 'index'])->name('insights.index');
    Route::post('/insights/generate', [SpendingInsightController::class, 'generate'])->name('insights.generate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/profile/telegram/link', [TelegramLinkController::class, 'link'])->name('profile.telegram.link');
    Route::delete('/profile/telegram/unlink', [TelegramLinkController::class, 'unlink'])->name('profile.telegram.unlink');
});

Route::post('/api/telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');
Route::post('/telegram/mini-app-auth', [TelegramMiniAppAuthController::class, 'authenticate'])->name('telegram.mini-app.auth');

require __DIR__.'/auth.php';
