<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpendingInsightController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::put('/locale/{locale}', [LocaleController::class, 'update'])
    ->whereIn('locale', ['en', 'id'])
    ->name('locale.update');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

    Route::resource('transactions', TransactionController::class);

    Route::get('/insights', [SpendingInsightController::class, 'index'])->name('insights.index');
    Route::post('/insights/generate', [SpendingInsightController::class, 'generate'])->name('insights.generate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
