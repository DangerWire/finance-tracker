<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramMessageRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming webhook updates from Telegram.
     */
    public function __invoke(Request $request, TelegramMessageRouter $router): JsonResponse
    {
        $expectedSecret = (string) config('services.telegram.webhook_secret', '');
        if (filled($expectedSecret)) {
            $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (! hash_equals($expectedSecret, $providedSecret)) {
                Log::warning('Telegram webhook rejected: invalid secret token');

                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $update = $request->all();

        try {
            $router->handleUpdate($update);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
