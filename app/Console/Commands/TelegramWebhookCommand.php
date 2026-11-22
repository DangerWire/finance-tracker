<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook
                            {action=info : Action: info, set, or delete}
                            {--url= : Custom HTTPS webhook URL (defaults to APP_URL/api/telegram/webhook)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect, register, or remove the production Telegram Bot webhook';

    /**
     * Execute the console command.
     */
    public function handle(TelegramBotService $bot): int
    {
        if (! $bot->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        $action = strtolower((string) $this->argument('action'));

        return match ($action) {
            'info' => $this->showInfo($bot),
            'set' => $this->registerWebhook($bot),
            'delete' => $this->removeWebhook($bot),
            default => $this->invalidAction($action),
        };
    }

    private function showInfo(TelegramBotService $bot): int
    {
        $me = $bot->getMe();
        $info = $bot->getWebhookInfo();

        if (! ($me['ok'] ?? false)) {
            $this->error('Failed to query bot identity: '.($me['description'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        $username = $me['result']['username'] ?? 'unknown';
        $result = $info['result'] ?? [];
        $webhookUrl = (string) ($result['url'] ?? '');
        $pending = (int) ($result['pending_update_count'] ?? 0);
        $lastError = $result['last_error_message'] ?? null;
        $secretConfigured = filled(config('services.telegram.webhook_secret')) ? 'Yes' : 'No (recommended in production)';

        $this->line("Bot Identity: @{$username}");
        $this->line('Webhook URL: '.($webhookUrl !== '' ? $webhookUrl : '(none — using long polling)'));
        $this->line("Pending Updates: {$pending}");
        $this->line("Webhook Secret Configured: {$secretConfigured}");

        if ($lastError) {
            $this->warn("Last Error: {$lastError}");
        }

        return self::SUCCESS;
    }

    private function registerWebhook(TelegramBotService $bot): int
    {
        $url = trim((string) ($this->option('url') ?: rtrim((string) config('app.url'), '/').'/api/telegram/webhook'));

        if (! str_starts_with($url, 'https://')) {
            $this->error("Telegram requires an HTTPS webhook URL. Received: {$url}");

            return self::FAILURE;
        }

        if (blank(config('services.telegram.webhook_secret'))) {
            $this->warn('Warning: TELEGRAM_WEBHOOK_SECRET is empty. Setting one in .env is strongly recommended for production.');
        }

        $response = $bot->setWebhook($url);

        if (! ($response['ok'] ?? false)) {
            $this->error('Failed to set webhook: '.($response['description'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        $this->info("Telegram webhook registered at: {$url}");

        return self::SUCCESS;
    }

    private function removeWebhook(TelegramBotService $bot): int
    {
        $response = $bot->deleteWebhook();

        if (! ($response['ok'] ?? false)) {
            $this->error('Failed to delete webhook: '.($response['description'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        $this->info('Telegram webhook removed successfully.');

        return self::SUCCESS;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Unknown action '{$action}'. Allowed actions: info, set, delete.");

        return self::FAILURE;
    }
}
