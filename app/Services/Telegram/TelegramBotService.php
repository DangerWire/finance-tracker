<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramBotService
{
    private string $botToken;

    private ?string $botUsername;

    private ?string $webhookSecret;

    public function __construct(
        ?string $botToken = null,
        ?string $botUsername = null,
        ?string $webhookSecret = null
    ) {
        $this->botToken = (string) ($botToken ?? config('services.telegram.bot_token', ''));
        $this->botUsername = (string) ($botUsername ?? config('services.telegram.bot_username', ''));
        $this->webhookSecret = (string) ($webhookSecret ?? config('services.telegram.webhook_secret', ''));
    }

    /**
     * Check if the Telegram Bot token is configured.
     */
    public function isConfigured(): bool
    {
        return filled($this->botToken);
    }

    /**
     * Get configured bot username.
     */
    public function getBotUsername(): ?string
    {
        return $this->botUsername;
    }

    /**
     * Fetch basic information about the bot.
     *
     * @return array<string, mixed>
     */
    public function getMe(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $response = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->timeout(10)
            ->get('/getMe');

        return $response->json() ?? [];
    }

    /**
     * Send a text message to a Telegram chat.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function sendMessage(int|string $chatId, string $text, array $extra = []): array
    {
        if (! $this->isConfigured()) {
            Log::info("TelegramBotService: skipped sendMessage because token is not set. Chat: {$chatId}, Text: {$text}");

            return ['ok' => true, 'mock' => true];
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $extra);

        $response = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->timeout(15)
            ->post('/sendMessage', $payload);

        $data = $response->json() ?? [];

        if ($response->failed() || ! ($data['ok'] ?? false)) {
            Log::warning('TelegramBotService: sendMessage failed', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'description' => $data['description'] ?? null,
            ]);
        }

        return $data;
    }

    /**
     * Fetch updates for long-polling runner.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(int $offset = 0, int $timeout = 25): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $response = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->timeout($timeout + 10)
            ->get('/getUpdates', [
                'offset' => $offset,
                'timeout' => $timeout,
                'allowed_updates' => json_encode(['message', 'callback_query']),
            ]);

        $data = $response->json() ?? [];

        if ($response->failed() || ! ($data['ok'] ?? false)) {
            $description = $data['description'] ?? 'HTTP '.$response->status();
            Log::error('TelegramBotService: getUpdates failed', [
                'status' => $response->status(),
                'description' => $description,
            ]);

            throw new RuntimeException('Telegram getUpdates failed: '.$description);
        }

        return (array) ($data['result'] ?? []);
    }

    /**
     * Register a webhook endpoint with Telegram.
     *
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $secret = $secretToken ?? $this->webhookSecret;
        $params = ['url' => $url];

        if (filled($secret)) {
            $params['secret_token'] = $secret;
        }

        $response = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->post('/setWebhook', $params);

        return $response->json() ?? [];
    }

    /**
     * Remove the registered webhook.
     *
     * @return array<string, mixed>
     */
    public function deleteWebhook(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $response = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->post('/deleteWebhook');

        return $response->json() ?? [];
    }

    /**
     * Fetch the current webhook configuration from Telegram.
     *
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $response = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->timeout(10)
            ->get('/getWebhookInfo');

        return $response->json() ?? [];
    }

    /**
     * Acknowledge a Telegram inline keyboard callback query.
     *
     * @return array<string, mixed>
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => true, 'mock' => true];
        }

        $payload = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert,
        ];

        if ($text !== null && $text !== '') {
            $payload['text'] = $text;
        }

        $response = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->timeout(10)
            ->post('/answerCallbackQuery', $payload);

        $data = $response->json() ?? [];

        if ($response->failed() || ! ($data['ok'] ?? false)) {
            Log::warning('TelegramBotService: answerCallbackQuery failed', [
                'callback_query_id' => $callbackQueryId,
                'status' => $response->status(),
                'description' => $data['description'] ?? null,
            ]);
        }

        return $data;
    }

    /**
     * Edit an existing Telegram message in-place.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function editMessageText(int|string $chatId, int $messageId, string $text, array $extra = []): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => true, 'mock' => true];
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $extra);

        $response = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->timeout(15)
            ->post('/editMessageText', $payload);

        $data = $response->json() ?? [];

        if ($response->failed() || ! ($data['ok'] ?? false)) {
            Log::warning('TelegramBotService: editMessageText failed', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'status' => $response->status(),
                'description' => $data['description'] ?? null,
            ]);
        }

        return $data;
    }

    /**
     * Download a Telegram file by file_id for multimodal inspection.
     *
     * @return array{content: string, mime_type: string, file_path: string}|null
     */
    public function downloadFile(string $fileId): ?array
    {
        if (! $this->isConfigured() || $fileId === '') {
            return null;
        }

        $metaResponse = Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->timeout(10)
            ->get('/getFile', ['file_id' => $fileId]);

        $meta = $metaResponse->json() ?? [];
        if ($metaResponse->failed() || ! ($meta['ok'] ?? false)) {
            Log::warning('TelegramBotService: getFile failed', [
                'status' => $metaResponse->status(),
                'description' => $meta['description'] ?? null,
            ]);

            return null;
        }

        $filePath = (string) ($meta['result']['file_path'] ?? '');
        $fileSize = (int) ($meta['result']['file_size'] ?? 0);

        if ($filePath === '' || $fileSize > 10_485_760) {
            return null;
        }

        $fileResponse = Http::timeout(20)
            ->get("https://api.telegram.org/file/bot{$this->botToken}/{$filePath}");

        if ($fileResponse->failed()) {
            Log::warning('TelegramBotService: file download failed', [
                'status' => $fileResponse->status(),
                'file_path' => $filePath,
            ]);

            return null;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            default => '',
        };

        if ($mimeType === '') {
            $headerMime = strtolower((string) $fileResponse->header('Content-Type'));
            $headerMime = trim(explode(';', $headerMime)[0]);
            $mimeType = ($headerMime !== '' && $headerMime !== 'application/octet-stream')
                ? $headerMime
                : 'image/jpeg';
        }

        return [
            'content' => $fileResponse->body(),
            'mime_type' => $mimeType,
            'file_path' => $filePath,
        ];
    }

    /**
     * Verify Telegram Mini App initData signature according to official Telegram spec.
     *
     * @return array<string, mixed>|null
     */
    public function verifyMiniAppInitData(string $initData): ?array
    {
        if (blank($initData) || ! $this->isConfigured()) {
            return null;
        }

        parse_str($initData, $params);

        if (! isset($params['hash'])) {
            return null;
        }

        $hash = $params['hash'];
        unset($params['hash']);

        // Check authentication date expiration (24h)
        if (isset($params['auth_date']) && (time() - (int) $params['auth_date'] > 86400)) {
            Log::warning('Telegram Mini App initData expired', ['auth_date' => $params['auth_date']]);

            return null;
        }

        ksort($params);

        $checkStringPieces = [];
        foreach ($params as $key => $val) {
            $checkStringPieces[] = "{$key}={$val}";
        }
        $dataCheckString = implode("\n", $checkStringPieces);

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($calculatedHash, $hash)) {
            Log::warning('Telegram Mini App initData HMAC mismatch');

            return null;
        }

        if (isset($params['user']) && is_string($params['user'])) {
            $params['user_data'] = json_decode($params['user'], true);
        }

        return $params;
    }
}
