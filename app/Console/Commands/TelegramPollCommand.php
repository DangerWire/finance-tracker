<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramMessageRouter;
use Illuminate\Console\Command;
use Throwable;

class TelegramPollCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:poll {--timeout=20 : Telegram long-polling timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a local Telegram long-polling listener to process updates without a webhook';

    /**
     * Execute the console command.
     */
    public function handle(TelegramBotService $bot, TelegramMessageRouter $router): int
    {
        if (! $bot->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env. Please configure your bot token first.');

            return self::FAILURE;
        }

        $timeout = (int) $this->option('timeout');
        $this->info("Starting Telegram long-polling listener (timeout: {$timeout}s)...");
        $this->info('Press Ctrl+C to stop.');

        $offset = 0;

        while (true) {
            try {
                $updates = $bot->getUpdates($offset, $timeout);

                foreach ($updates as $update) {
                    $updateId = $update['update_id'] ?? 0;
                    $offset = $updateId + 1;

                    if (! empty($update['callback_query'])) {
                        $from = $update['callback_query']['from']['username']
                            ?? $update['callback_query']['from']['first_name']
                            ?? 'Unknown';
                        $text = '[callback] '.($update['callback_query']['data'] ?? '');
                    } else {
                        $from = $update['message']['from']['username']
                            ?? $update['message']['from']['first_name']
                            ?? 'Unknown';
                        if (! empty($update['message']['photo'])) {
                            $text = '[photo] '.($update['message']['caption'] ?? '');
                        } elseif (! empty($update['message']['document'])) {
                            $text = '[document] '.($update['message']['document']['file_name'] ?? 'file');
                        } else {
                            $text = $update['message']['text'] ?? '[non-text]';
                        }
                    }

                    $this->line('[<fg=gray>'.now()->format('H:i:s')."</>] Update #{$updateId} from @{$from}: ".trim((string) $text));

                    $router->handleUpdate($update);
                }
            } catch (Throwable $e) {
                $this->error('Error during polling: '.$e->getMessage());
                sleep(2);
            }
        }

        return self::SUCCESS;
    }
}
