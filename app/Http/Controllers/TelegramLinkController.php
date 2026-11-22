<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TelegramLinkController extends Controller
{
    /**
     * Generate a new connection token for the authenticated user.
     */
    public function link(Request $request): RedirectResponse
    {
        $request->user()->generateTelegramLinkToken();

        return redirect()->route('profile.edit')->with('status', 'telegram-link-token-generated');
    }

    /**
     * Disconnect the authenticated user's Telegram account.
     */
    public function unlink(Request $request): RedirectResponse
    {
        $request->user()->unlinkTelegram();

        return redirect()->route('profile.edit')->with('status', 'telegram-unlinked');
    }
}
