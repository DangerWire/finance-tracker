<?php

namespace App\Http\Controllers;

use App\Actions\ConvertTransactionAmount;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'currencies' => config('finance.supported_currencies', []),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, ConvertTransactionAmount $convert): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $oldCurrency = $user->currency;
        $newCurrency = strtoupper((string) $validated['currency']);
        $recalculate = (bool) ($validated['recalculate_existing'] ?? true);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'currency' => $newCurrency,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($recalculate && $oldCurrency !== $newCurrency) {
            $user->transactions()->chunk(100, function ($transactions) use ($convert, $newCurrency) {
                foreach ($transactions as $transaction) {
                    $convert->recalculate($transaction, $newCurrency);
                    $transaction->save();
                }
            });
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
