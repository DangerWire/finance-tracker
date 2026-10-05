<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpendingInsightController;
use App\Http\Controllers\TransactionController;
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
    Route::resource('transactions', TransactionController::class);

    Route::get('/insights', [SpendingInsightController::class, 'index'])->name('insights.index');
    Route::post('/insights/generate', [SpendingInsightController::class, 'generate'])->name('insights.generate');
    Route::put('/insights/preference', [SpendingInsightController::class, 'updatePreference'])->name('insights.preference');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
