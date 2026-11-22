<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelegramMiniAppAuthController extends Controller
{
    /**
     * Authenticate a Telegram Mini App session using cryptographic initData signature.
     */
    public function authenticate(Request $request, TelegramBotService $bot): JsonResponse
    {
        $initData = (string) $request->input('init_data', '');
        if ($initData === '') {
            return response()->json(['error' => 'Missing init_data parameter'], 422);
        }

        $verified = $bot->verifyMiniAppInitData($initData);
        if ($verified === null) {
            return response()->json(['error' => 'Invalid or expired Telegram signature'], 401);
        }

        $telegramUser = $verified['user_data'] ?? null;
        $telegramId = $telegramUser['id'] ?? null;

        if (! $telegramId) {
            return response()->json(['error' => 'No Telegram user found in payload'], 422);
        }

        $user = User::where('telegram_chat_id', $telegramId)->first();
        if (! $user) {
            return response()->json([
                'error' => 'Telegram account is not linked to any Finance Tracker user. Please connect your account first in web Profile Settings.',
                'code' => 'NOT_LINKED',
            ], 404);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => route('dashboard'),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
