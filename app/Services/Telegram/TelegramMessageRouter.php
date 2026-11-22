<?php

namespace App\Services\Telegram;

use App\Actions\ConvertTransactionAmount;
use App\Actions\SettleDebtAction;
use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Debt;
use App\Models\User;
use App\Services\SpendingStatsService;
use App\Services\TransactionImportParser;
use App\Services\TransactionImportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TelegramMessageRouter
{
    private const IMPORT_STAGING_DIR = 'imports';

    public function __construct(
        protected TelegramBotService $bot,
        protected TelegramAiParser $aiParser,
        protected ConvertTransactionAmount $convert,
        protected SpendingStatsService $stats,
        protected SettleDebtAction $settleDebt,
        protected TelegramBudgetAlertService $budgetAlerts,
        protected TransactionImportParser $importParser,
        protected TransactionImportService $importer,
    ) {}

    /**
     * Handle an incoming Telegram update.
     *
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update): void
    {
        if (! empty($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        if (! $message || empty($message['chat']['id'])) {
            return;
        }

        $chatId = $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));
        $from = $message['from'] ?? [];
        $username = $from['username'] ?? ($from['first_name'] ?? null);

        // Handle receipt photo scanning before checking text commands
        if (! empty($message['photo']) && is_array($message['photo'])) {
            $user = User::where('telegram_chat_id', $chatId)->first();
            if (! $user) {
                $this->sendAccountNotLinkedMessage($chatId);

                return;
            }

            $caption = isset($message['caption']) ? trim((string) $message['caption']) : null;
            $this->handlePhotoReceipt($chatId, $user, $message['photo'], $caption);

            return;
        }

        // Handle CSV file upload for bulk transaction import
        if (! empty($message['document']) && is_array($message['document'])) {
            $user = User::where('telegram_chat_id', $chatId)->first();
            if (! $user) {
                $this->sendAccountNotLinkedMessage($chatId);

                return;
            }

            $this->handleCsvDocument($chatId, $user, $message['document']);

            return;
        }

        if ($text === '') {
            return;
        }

        // 1. /start [token]
        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId, $text, $username);

            return;
        }

        // 2. /help
        if (str_starts_with($text, '/help')) {
            $this->handleHelp($chatId);

            return;
        }

        // 3. /import guide
        if (str_starts_with($text, '/import')) {
            $this->handleImportGuide($chatId);

            return;
        }

        // Find linked user for all authenticated commands
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (! $user) {
            $this->sendAccountNotLinkedMessage($chatId);

            return;
        }

        // 3. /balance
        if (str_starts_with($text, '/balance')) {
            $this->handleBalance($chatId, $user);

            return;
        }

        // 4. /recent
        if (str_starts_with($text, '/recent')) {
            $this->handleRecent($chatId, $user);

            return;
        }

        // 5. /budgets
        if (str_starts_with($text, '/budgets')) {
            $this->handleBudgets($chatId, $user);

            return;
        }

        // 6. /debts
        if (str_starts_with($text, '/debts')) {
            $this->handleDebts($chatId, $user);

            return;
        }

        // 7. /expense <amount> [category] [notes]
        if (str_starts_with($text, '/expense')) {
            $this->handleExplicitCommand($chatId, $user, $text, TransactionType::Expense);

            return;
        }

        // 8. /income <amount> [category] [notes]
        if (str_starts_with($text, '/income')) {
            $this->handleExplicitCommand($chatId, $user, $text, TransactionType::Income);

            return;
        }

        // 9. Natural language / conversational message
        $this->handleNaturalLanguage($chatId, $user, $text);
    }

    /**
     * Notify unlinked chat users how to connect their account.
     */
    protected function sendAccountNotLinkedMessage(int|string $chatId): void
    {
        $this->bot->sendMessage(
            $chatId,
            "🔒 <b>Account Not Linked</b>\n\nPlease connect your Finance Tracker account first:\n1. Log in to your web dashboard\n2. Open <b>Profile & Settings</b>\n3. Click <b>Connect Telegram</b> and follow the link!"
        );
    }

    /**
     * Handle /start [token] command.
     */
    protected function handleStart(int|string $chatId, string $text, ?string $username): void
    {
        $parts = explode(' ', $text, 2);
        $token = isset($parts[1]) ? trim($parts[1]) : null;

        if (filled($token)) {
            $user = User::where('telegram_link_token', $token)
                ->where('telegram_link_token_expires_at', '>', now())
                ->first();

            if ($user) {
                $user->update([
                    'telegram_chat_id' => $chatId,
                    'telegram_username' => $username,
                    'telegram_link_token' => null,
                    'telegram_link_token_expires_at' => null,
                ]);

                $footer = $this->hasHttpsAppUrl()
                    ? "\n\nTap below to open your full dashboard right inside Telegram!"
                    : "\n\n<i>Mini App button activates automatically once APP_URL uses HTTPS.</i>";

                $this->bot->sendMessage(
                    $chatId,
                    "🎉 <b>Account Linked Successfully!</b>\n\nWelcome, <b>".e($user->name)."</b>! Your Telegram account is now securely linked to Finance Tracker.\n\n<b>Try these anytime:</b>\n• <code>/balance</code> — Monthly overview\n• <code>/budgets</code> — Monthly budgets &amp; progress\n• <code>/debts</code> — Peer-to-peer debts &amp; actions\n• <code>/recent</code> — Latest transactions\n• <code>/expense 50 Food lunch</code> — Quick expense\n• <code>/income 5000 Salary</code> — Quick income\n• Send a receipt photo to scan &amp; log it automatically\n• Or just type naturally: <i>'spent 35k on coffee'</i> or <i>'makan padang 25rb'</i>{$footer}",
                    $this->miniAppExtra()
                );

                return;
            }

            $this->bot->sendMessage(
                $chatId,
                "⚠️ <b>Invalid or Expired Link Token</b>\n\nThis connection token is invalid or has expired (valid for 15 minutes).\n\nPlease visit your <b>Profile Settings</b> on the web to generate a fresh link."
            );

            return;
        }

        $existingUser = User::where('telegram_chat_id', $chatId)->first();
        if ($existingUser) {
            $cta = $this->hasHttpsAppUrl()
                ? ', or tap below to launch the Mini App.'
                : '.';

            $this->bot->sendMessage(
                $chatId,
                '👋 Welcome back, <b>'.e($existingUser->name)."</b>!\n\nYour account is connected to Finance Tracker.\n\nType <code>/help</code> for available commands{$cta}",
                $this->miniAppExtra()
            );

            return;
        }

        $this->bot->sendMessage(
            $chatId,
            "👋 <b>Welcome to Finance Tracker!</b>\n\nTo connect your Telegram account:\n1. Log in to your Finance Tracker web dashboard\n2. Open <b>Profile & Settings</b>\n3. Click <b>Connect Telegram</b> and follow the link!"
        );
    }

    /**
     * Handle /help command.
     */
    protected function handleHelp(int|string $chatId): void
    {
        $lines = [
            '📖 <b>Finance Tracker Bot Commands</b>',
            '',
            '• <code>/balance</code> — Monthly summary (income, expenses, net)',
            '• <code>/budgets</code> — Monthly budgets &amp; category progress',
            '• <code>/debts</code> — Peer-to-peer debts &amp; one-tap actions',
            '• <code>/recent</code> — Last 5 transactions',
            '• <code>/import</code> — How to bulk-import a CSV statement here',
            '• <code>/expense [amount] [category] [notes]</code> — Log expense',
            '• <code>/income [amount] [category] [notes]</code> — Log income',
            '',
            '📄 <b>CSV Bulk Import:</b>',
            'Attach and send any <code>.csv</code> bank, Alipay, WeChat Pay, or spreadsheet file to preview and import it with one tap.',
            '',
            '📸 <b>Receipt Scanning:</b>',
            'Send a photo of any receipt (with an optional caption) to scan &amp; log it via Gemini AI.',
            '',
            '💡 <b>Natural Language / AI:</b>',
            'You can also simply type naturally in English or Indonesian:',
            '• <i>"spent 35k on coffee"</i>',
            '• <i>"makan nasi padang 25.000"</i>',
            '• <i>"gajian 8.5jt kantor"</i>',
            '• <i>"grab to office 32.50"</i>',
        ];

        if ($this->hasHttpsAppUrl()) {
            $lines[] = '';
            $lines[] = '📱 <b>Mini App:</b>';
            $lines[] = 'Tap the button below to use the full visual dashboard directly inside Telegram.';
        }

        $this->bot->sendMessage($chatId, implode("\n", $lines), $this->miniAppExtra());
    }

    /**
     * Determine whether APP_URL uses HTTPS (required by Telegram for inline web_app buttons).
     */
    protected function hasHttpsAppUrl(): bool
    {
        return str_starts_with((string) config('app.url'), 'https://');
    }

    /**
     * Build the Mini App inline keyboard payload when HTTPS is configured.
     *
     * @return array<string, mixed>
     */
    protected function miniAppExtra(): array
    {
        if (! $this->hasHttpsAppUrl()) {
            return [];
        }

        return [
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🚀 Open Finance Tracker Mini App',
                            'web_app' => ['url' => config('app.url')],
                        ],
                    ],
                ],
            ]),
        ];
    }

    /**
     * Handle /balance command.
     */
    protected function handleBalance(int|string $chatId, User $user): void
    {
        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfMonth();
        $userCurrency = strtoupper((string) $user->currency);

        $summary = $user->transactions()
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('base_amount')
            ->where('base_currency', $userCurrency)
            ->selectRaw('type, SUM(base_amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = round((float) ($summary[TransactionType::Income->value] ?? 0), 4);
        $expenses = round((float) ($summary[TransactionType::Expense->value] ?? 0), 4);
        $balance = round($income - $expenses, 4);

        $excludedCount = $user->transactions()
            ->whereBetween('occurred_at', [$start, $end])
            ->where(fn ($query) => $query
                ->whereNull('base_amount')
                ->orWhere('base_currency', '!=', $userCurrency))
            ->count();

        $monthName = now()->format('F Y');

        $lines = [
            "📊 <b>Summary for {$monthName}</b>",
            '',
            '🟢 Income: <b>'.number_format($income, 2)." {$userCurrency}</b>",
            '🔴 Expenses: <b>'.number_format($expenses, 2)." {$userCurrency}</b>",
            '━━━━━━━━━━━━━━━━━',
            '💰 Net Balance: <b>'.number_format($balance, 2)." {$userCurrency}</b>",
        ];

        if ($excludedCount > 0) {
            $lines[] = '';
            $lines[] = "ℹ️ {$excludedCount} unconverted transaction(s) excluded.";
        }

        $this->bot->sendMessage($chatId, implode("\n", $lines));
    }

    /**
     * Handle /recent command.
     */
    protected function handleRecent(int|string $chatId, User $user): void
    {
        $recent = $user->transactions()
            ->latest('occurred_at')
            ->take(5)
            ->get();

        if ($recent->isEmpty()) {
            $this->bot->sendMessage($chatId, 'ℹ️ No transactions recorded yet.');

            return;
        }

        $lines = ['🕒 <b>Recent Transactions:</b>', ''];
        foreach ($recent as $t) {
            $icon = $t->type === TransactionType::Income ? '🟢' : '🔴';
            $amt = number_format((float) $t->amount, 2);
            $date = $t->occurred_at ? $t->occurred_at->format('M d') : '';
            $note = $t->note ? ' — <i>'.e($t->note).'</i>' : '';
            $lines[] = "{$icon} <b>{$amt} {$t->currency}</b> • ".e($t->category)." ({$date}){$note}";
        }

        $this->bot->sendMessage($chatId, implode("\n", $lines));
    }

    /**
     * Handle /budgets command: render current month's category budgets and progress bars.
     */
    protected function handleBudgets(int|string $chatId, User $user): void
    {
        $now = CarbonImmutable::now();
        $overview = $this->stats->budgetOverview($user->id, $now);

        if ($overview['items'] === []) {
            $this->bot->sendMessage(
                $chatId,
                "📊 <b>Monthly Budgets ({$overview['month']})</b>\n\nNo budgets set for this month yet. Set category targets in the web app under <b>Budgets</b>."
            );

            return;
        }

        $currency = $overview['currency'];
        $overallBar = $this->progressBar((float) $overview['percentage_used']);

        $lines = [
            "📊 <b>Monthly Budgets ({$overview['month']})</b>",
            '',
            'Overall: <b>'.number_format((float) $overview['total_spent'], 2).' / '.number_format((float) $overview['total_budget'], 2)." {$currency}</b> (".number_format((float) $overview['percentage_used'], 1).'%)',
            "<code>{$overallBar}</code>",
            '',
        ];

        foreach ($overview['items'] as $item) {
            $icon = match ($item['status']) {
                'exceeded' => '🔴',
                'warning' => '🟡',
                default => '🟢',
            };
            $bar = $this->progressBar((float) $item['percentage_used'], 8);
            $spentFmt = number_format((float) $item['spent_amount'], 2);
            $budgetFmt = number_format((float) $item['budget_amount'], 2);
            $pctFmt = number_format((float) $item['percentage_used'], 1);

            $lines[] = "{$icon} <b>".e($item['category'])."</b> — {$spentFmt} / {$budgetFmt} {$currency} ({$pctFmt}%)";
            $lines[] = "<code>{$bar}</code>";
        }

        $this->bot->sendMessage($chatId, implode("\n", $lines));
    }

    /**
     * Render a compact Unicode progress bar for Telegram HTML messages.
     */
    protected function progressBar(float $percentage, int $width = 10): string
    {
        $clamped = max(0.0, min(100.0, $percentage));
        $filled = (int) round(($clamped / 100.0) * $width);

        return str_repeat('▓', $filled).str_repeat('░', max(0, $width - $filled));
    }

    /**
     * Handle /debts command: list open peer-to-peer debts with inline action buttons.
     */
    protected function handleDebts(int|string $chatId, User $user): void
    {
        $openStatuses = [
            DebtStatus::PendingAcceptance->value,
            DebtStatus::Active->value,
            DebtStatus::PaymentSubmitted->value,
        ];

        $openDebts = Debt::query()
            ->where(fn ($q) => $q
                ->where('lender_id', $user->id)
                ->orWhere('borrower_id', $user->id))
            ->whereIn('status', $openStatuses)
            ->with(['lender', 'borrower', 'creator'])
            ->latest()
            ->get();

        $outstanding = $openDebts->filter(
            fn (Debt $debt): bool => in_array($debt->status, [DebtStatus::Active, DebtStatus::PaymentSubmitted], true)
        );

        $userCurrency = strtoupper((string) $user->currency);
        $totalLent = round((float) $outstanding->where('lender_id', $user->id)->sum('amount'), 2);
        $totalBorrowed = round((float) $outstanding->where('borrower_id', $user->id)->sum('amount'), 2);
        $netPosition = round($totalLent - $totalBorrowed, 2);

        $lines = [
            '🤝 <b>Peer-to-Peer Debts</b>',
            '',
            '🟢 Total Lent: <b>+'.number_format($totalLent, 2)." {$userCurrency}</b>",
            '🔴 Total Borrowed: <b>−'.number_format($totalBorrowed, 2)." {$userCurrency}</b>",
            '⚖️ Net Position: <b>'.($netPosition >= 0 ? '+' : '−').number_format(abs($netPosition), 2)." {$userCurrency}</b>",
        ];

        $inlineKeyboard = [];

        if ($openDebts->isEmpty()) {
            $lines[] = '';
            $lines[] = 'ℹ️ No pending or active peer-to-peer debts.';
        } else {
            $lines[] = '';
            $lines[] = '<b>Open Items:</b>';

            foreach ($openDebts as $debt) {
                $isLender = $debt->lender_id === $user->id;
                $counterparty = $isLender ? ($debt->borrower?->name ?? 'Friend') : ($debt->lender?->name ?? 'Friend');
                $directionLabel = $isLender ? 'Lent to' : 'Borrowed from';
                $amtFmt = number_format((float) $debt->amount, 2);
                $note = $debt->note ? ' — <i>'.e($debt->note).'</i>' : '';

                $lines[] = "• <b>#{$debt->id}</b> {$directionLabel} <b>".e($counterparty)."</b>: {$amtFmt} {$debt->currency} [<i>".e($debt->status->label())."</i>]{$note}";

                if ($debt->status === DebtStatus::PendingAcceptance && $debt->creator_id !== $user->id) {
                    $inlineKeyboard[] = [
                        ['text' => "✅ Accept (#{$debt->id})", 'callback_data' => "debt:accept:{$debt->id}"],
                        ['text' => "❌ Decline (#{$debt->id})", 'callback_data' => "debt:decline:{$debt->id}"],
                    ];
                } elseif ($debt->status === DebtStatus::Active && $debt->borrower_id === $user->id) {
                    $inlineKeyboard[] = [
                        ['text' => "💸 Mark Paid (#{$debt->id})", 'callback_data' => "debt:pay:{$debt->id}"],
                    ];
                } elseif ($debt->status === DebtStatus::PaymentSubmitted && $debt->lender_id === $user->id) {
                    $inlineKeyboard[] = [
                        ['text' => "✅ Confirm (#{$debt->id})", 'callback_data' => "debt:confirm:{$debt->id}"],
                        ['text' => "⚠️ Dispute (#{$debt->id})", 'callback_data' => "debt:dispute:{$debt->id}"],
                    ];
                }
            }
        }

        $extra = $inlineKeyboard !== []
            ? ['reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard])]
            : [];

        $this->bot->sendMessage($chatId, implode("\n", $lines), $extra);
    }

    /**
     * Handle inline keyboard callback queries for debt workflow actions.
     *
     * @param  array<string, mixed>  $callbackQuery
     */
    protected function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $message = is_array($callbackQuery['message'] ?? null) ? $callbackQuery['message'] : [];
        $chatId = $message['chat']['id'] ?? ($callbackQuery['from']['id'] ?? null);
        $messageId = isset($message['message_id']) ? (int) $message['message_id'] : null;

        if (preg_match('/^import:(confirm|force|cancel):([A-Za-z0-9-]+)$/', $data, $importMatches)) {
            $this->handleImportCallback($callbackId, $chatId, $messageId, $importMatches[1], $importMatches[2]);

            return;
        }

        if (! preg_match('/^debt:(accept|decline|pay|confirm|dispute):(\d+)$/', $data, $matches)) {
            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'Unknown action.');
            }

            return;
        }

        $action = $matches[1];
        $debtId = (int) $matches[2];

        $user = $chatId !== null ? User::where('telegram_chat_id', $chatId)->first() : null;
        if (! $user) {
            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'Please link your Finance Tracker account first.', true);
            }

            return;
        }

        $debt = Debt::with(['lender', 'borrower', 'creator'])->find($debtId);
        if (! $debt) {
            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'Debt record not found.', true);
            }

            return;
        }

        $authorized = match ($action) {
            'accept' => $debt->isParticipant($user) && $debt->creator_id !== $user->id && $debt->status === DebtStatus::PendingAcceptance,
            'decline' => $debt->isParticipant($user) && $debt->status === DebtStatus::PendingAcceptance,
            'pay' => $debt->borrower_id === $user->id && $debt->status === DebtStatus::Active,
            'confirm', 'dispute' => $debt->lender_id === $user->id && $debt->status === DebtStatus::PaymentSubmitted,
        };

        if (! $authorized) {
            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'This action is no longer valid or you are not authorized.', true);
            }

            return;
        }

        $counterparty = $debt->lender_id === $user->id ? $debt->borrower : $debt->lender;
        $amtFmt = number_format((float) $debt->amount, 2);

        switch ($action) {
            case 'accept':
                $debt->update([
                    'status' => DebtStatus::Active,
                    'accepted_at' => now(),
                ]);
                $toast = 'Loan proposal accepted!';
                $updatedText = "✅ <b>Loan #{$debt->id} Accepted</b>\n\n{$amtFmt} {$debt->currency} with ".e($counterparty?->name ?? 'Friend').' is now <b>Active</b>.';
                $counterpartyNotice = '✅ <b>'.e($user->name)."</b> accepted loan proposal <b>#{$debt->id}</b> ({$amtFmt} {$debt->currency}).";
                break;

            case 'decline':
                $debt->update(['status' => DebtStatus::Declined]);
                $toast = 'Loan proposal declined.';
                $updatedText = "❌ <b>Loan #{$debt->id} Declined</b>\n\nLoan proposal ({$amtFmt} {$debt->currency}) with ".e($counterparty?->name ?? 'Friend').' was declined.';
                $counterpartyNotice = '❌ <b>'.e($user->name)."</b> declined loan proposal <b>#{$debt->id}</b> ({$amtFmt} {$debt->currency}).";
                break;

            case 'pay':
                $debt->update([
                    'status' => DebtStatus::PaymentSubmitted,
                    'payment_submitted_at' => now(),
                ]);
                $toast = 'Payment submitted for confirmation!';
                $updatedText = "💸 <b>Payment Submitted for Loan #{$debt->id}</b>\n\n{$amtFmt} {$debt->currency} is awaiting confirmation from <b>".e($counterparty?->name ?? 'Lender').'</b>.';
                $counterpartyNotice = '💸 <b>'.e($user->name)."</b> marked loan <b>#{$debt->id}</b> ({$amtFmt} {$debt->currency}) as paid. Run <code>/debts</code> to confirm or dispute receipt.";
                break;

            case 'confirm':
                $this->settleDebt->execute($debt);
                $toast = 'Payment confirmed and loan settled!';
                $updatedText = "🎉 <b>Loan #{$debt->id} Settled!</b>\n\nPayment of {$amtFmt} {$debt->currency} from <b>".e($counterparty?->name ?? 'Borrower').'</b> is confirmed and synced to both ledgers.';
                $counterpartyNotice = '🎉 <b>'.e($user->name)."</b> confirmed your repayment for loan <b>#{$debt->id}</b> ({$amtFmt} {$debt->currency}). Both ledgers have been updated.";
                break;

            case 'dispute':
                $debt->update([
                    'status' => DebtStatus::Active,
                    'payment_submitted_at' => null,
                ]);
                $toast = 'Payment disputed; loan returned to active.';
                $updatedText = "⚠️ <b>Payment Disputed for Loan #{$debt->id}</b>\n\n{$amtFmt} {$debt->currency} with <b>".e($counterparty?->name ?? 'Borrower').'</b> has been returned to <b>Active</b>.';
                $counterpartyNotice = '⚠️ <b>'.e($user->name)."</b> disputed the payment submission for loan <b>#{$debt->id}</b> ({$amtFmt} {$debt->currency}). Loan remains active.";
                break;
        }

        if ($callbackId !== '') {
            $this->bot->answerCallbackQuery($callbackId, $toast);
        }

        if ($chatId !== null && $messageId !== null) {
            $this->bot->editMessageText($chatId, $messageId, $updatedText, [
                'reply_markup' => json_encode(['inline_keyboard' => []]),
            ]);
        }

        if ($counterparty && $counterparty->isTelegramLinked() && (string) $counterparty->telegram_chat_id !== (string) $chatId) {
            $this->bot->sendMessage((int) $counterparty->telegram_chat_id, $counterpartyNotice);
        }
    }

    /**
     * Handle receipt photo messages using Gemini Vision OCR.
     *
     * @param  array<int, array<string, mixed>>  $photos
     */
    protected function handlePhotoReceipt(int|string $chatId, User $user, array $photos, ?string $caption = null): void
    {
        $largest = end($photos);
        $fileId = is_array($largest) ? (string) ($largest['file_id'] ?? '') : '';

        if ($fileId === '') {
            $this->bot->sendMessage($chatId, '⚠️ Could not read that photo. Please try sending the receipt image again.');

            return;
        }

        $downloaded = $this->bot->downloadFile($fileId);
        if ($downloaded === null) {
            $this->bot->sendMessage($chatId, '⚠️ Failed to download the receipt photo from Telegram.');

            return;
        }

        $parsed = $this->aiParser->parseReceiptImage($downloaded['content'], $downloaded['mime_type'], $caption);
        if (! $parsed || empty($parsed['amount'])) {
            $this->bot->sendMessage(
                $chatId,
                "🤔 I couldn't read a transaction total from that receipt.\n\nEnsure <code>GEMINI_API_KEY</code> is set and the total amount is clearly visible, or log manually with <code>/expense 50 Food</code>."
            );

            return;
        }

        $type = $parsed['type'] === TransactionType::Income->value ? TransactionType::Income : TransactionType::Expense;

        $attributes = [
            'amount' => $parsed['amount'],
            'type' => $type->value,
            'currency' => $user->currency,
            'category' => $parsed['category'],
            'note' => $parsed['note'],
            'occurred_at' => $parsed['occurred_at'],
        ];

        $converted = $this->convert->apply($attributes, $user->currency);
        $transaction = $user->transactions()->create($converted);

        $icon = $type === TransactionType::Income ? '🟢 Income' : '🔴 Expense';
        $formattedAmount = number_format((float) $transaction->amount, 2);

        $this->bot->sendMessage($chatId, implode("\n", [
            '📸 <b>Receipt Scanned &amp; Recorded!</b>',
            '',
            "Type: {$icon}",
            "Amount: <b>{$formattedAmount} {$transaction->currency}</b>",
            'Category: <b>'.e($transaction->category).'</b>',
            'Note: <i>'.e($transaction->note).'</i>',
            'Date: '.$transaction->occurred_at->format('Y-m-d'),
            '',
            '⚡ <i>Processed via Gemini Vision</i>',
        ]));

        $this->budgetAlerts->checkForTransaction($transaction);
    }

    /**
     * Handle explicit /expense and /income commands.
     */
    protected function handleExplicitCommand(int|string $chatId, User $user, string $text, TransactionType $type): void
    {
        // Format: /expense 50 [category] [notes]
        $parts = preg_split('/\s+/', trim($text));
        array_shift($parts); // remove command

        if (empty($parts)) {
            $cmd = $type === TransactionType::Expense ? '/expense' : '/income';
            $this->bot->sendMessage($chatId, "⚠️ Usage: <code>{$cmd} &lt;amount&gt; [category] [note]</code>\nExample: <code>{$cmd} 45.000 Food lunch</code>");

            return;
        }

        $amountRaw = array_shift($parts);
        $amount = $this->aiParser->extractAmount($amountRaw);

        if ($amount === null || $amount <= 0) {
            $this->bot->sendMessage($chatId, '⚠️ Please provide a valid positive amount.');

            return;
        }

        $category = ! empty($parts) ? array_shift($parts) : 'General';
        $note = ! empty($parts) ? implode(' ', $parts) : ($type === TransactionType::Expense ? 'Expense' : 'Income');

        $attributes = [
            'amount' => $amount,
            'type' => $type->value,
            'currency' => $user->currency,
            'category' => $category,
            'note' => $note,
            'occurred_at' => now(),
        ];

        $converted = $this->convert->apply($attributes, $user->currency);
        $transaction = $user->transactions()->create($converted);

        $icon = $type === TransactionType::Income ? '🟢 Income' : '🔴 Expense';
        $formattedAmount = number_format($transaction->amount, 2);

        $text = implode("\n", [
            '✅ <b>Transaction Recorded!</b>',
            '',
            "Type: {$icon}",
            "Amount: <b>{$formattedAmount} {$transaction->currency}</b>",
            'Category: <b>'.e($transaction->category).'</b>',
            'Note: <i>'.e($transaction->note).'</i>',
        ]);

        $this->bot->sendMessage($chatId, $text);
        $this->budgetAlerts->checkForTransaction($transaction);
    }

    /**
     * Handle conversational / natural language message with AI parser.
     */
    protected function handleNaturalLanguage(int|string $chatId, User $user, string $text): void
    {
        $parsed = $this->aiParser->parse($text);

        if (! $parsed || empty($parsed['amount'])) {
            $this->bot->sendMessage(
                $chatId,
                "🤔 I couldn't identify the amount or transaction details.\n\nTry phrases like:\n• <i>'spent 35k on coffee'</i>\n• <i>'makan siang 45.000'</i>\n• <i>'gajian 8jt kantor'</i>\n\nOr use commands like <code>/expense 50 Food</code>."
            );

            return;
        }

        $type = $parsed['type'] === TransactionType::Income->value ? TransactionType::Income : TransactionType::Expense;

        $attributes = [
            'amount' => $parsed['amount'],
            'type' => $type->value,
            'currency' => $user->currency,
            'category' => $parsed['category'],
            'note' => $parsed['note'],
            'occurred_at' => now(),
        ];

        $converted = $this->convert->apply($attributes, $user->currency);
        $transaction = $user->transactions()->create($converted);

        $icon = $type === TransactionType::Income ? '🟢 Income' : '🔴 Expense';
        $formattedAmount = number_format($transaction->amount, 2);
        $methodLabel = $parsed['method'] === 'ai' ? 'Gemini AI' : 'Smart Parser';

        $reply = implode("\n", [
            '✅ <b>Transaction Recorded!</b>',
            '',
            "Type: {$icon}",
            "Amount: <b>{$formattedAmount} {$transaction->currency}</b>",
            'Category: <b>'.e($transaction->category).'</b>',
            'Note: <i>'.e($transaction->note).'</i>',
            '',
            "⚡ <i>Processed via {$methodLabel}</i>",
        ]);

        $this->bot->sendMessage($chatId, $reply);
        $this->budgetAlerts->checkForTransaction($transaction);
    }

    /**
     * Explain how to import CSV statements directly inside Telegram.
     */
    protected function handleImportGuide(int|string $chatId): void
    {
        $message = implode("\n", [
            '📄 <b>CSV Bulk Import via Telegram</b>',
            '',
            'Simply <b>attach and send a <code>.csv</code> file</b> directly in this chat!',
            '',
            '<b>How it works:</b>',
            '1. Tap the paperclip icon and send your bank, Alipay, WeChat Pay, or spreadsheet <code>.csv</code> export (UTF-8 or GBK/GB18030 supported).',
            '2. I automatically detect columns (Date, Amount, Type, Currency, Category, Note) and flag any duplicate rows.',
            '3. Review the preview summary and tap <b>✅ Import</b> (or <b>❌ Cancel</b>).',
            '',
            '<i>Tip: Every Telegram import is saved as a batch that you can review or undo anytime under Transactions → Import → Past imports on the web.</i>',
        ]);

        $this->bot->sendMessage($chatId, $message);
    }

    /**
     * Parse, validate, stage, and preview a CSV document uploaded in Telegram chat.
     *
     * @param  array<string, mixed>  $document
     */
    protected function handleCsvDocument(int|string $chatId, User $user, array $document): void
    {
        $fileId = (string) ($document['file_id'] ?? '');
        $filename = (string) ($document['file_name'] ?? 'statement.csv');
        $fileSize = (int) ($document['file_size'] ?? 0);
        $mimeType = strtolower((string) ($document['mime_type'] ?? ''));

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isCsvLike = in_array($ext, ['csv', 'txt'], true)
            || ($ext === '' && in_array($mimeType, ['text/csv', 'text/plain', 'application/csv'], true));

        if (! $isCsvLike) {
            $this->bot->sendMessage(
                $chatId,
                "⚠️ <b>Unsupported File Format</b>\n\nOnly <code>.csv</code> or <code>.txt</code> statement files can be imported here.\n\n• For bank statements: upload a <code>.csv</code> file.\n• For receipts: send it as a <b>Photo</b> instead of a document."
            );

            return;
        }

        if ($fileSize > 10_485_760) {
            $this->bot->sendMessage($chatId, '⚠️ File is too large (maximum 10 MB). Please split the export or import via the web dashboard.');

            return;
        }

        $downloaded = $this->bot->downloadFile($fileId);
        if ($downloaded === null) {
            $this->bot->sendMessage($chatId, '⚠️ Could not download that file from Telegram. Please try uploading it again.');

            return;
        }

        try {
            $parsed = $this->importParser->parseContent($downloaded['content']);
        } catch (RuntimeException $e) {
            $this->bot->sendMessage($chatId, '⚠️ Could not read CSV file: '.e($e->getMessage()));

            return;
        }

        $mapping = array_filter(
            $this->importer->guessMapping($parsed['headers']),
            fn ($value): bool => $value !== null && $value !== ''
        );

        if (empty($mapping['date']) || empty($mapping['amount'])) {
            $headersPreview = implode(', ', array_map(fn (string $h): string => '<code>'.e($h).'</code>', array_slice($parsed['headers'], 0, 8)));

            $this->bot->sendMessage(
                $chatId,
                "⚠️ <b>Could Not Auto-Detect Columns</b>\n\nI couldn't automatically identify both the <b>Date</b> and <b>Amount</b> columns in <code>".e($filename)."</code>.\n\nDetected headers: {$headersPreview}\n\nPlease upload this file on the web dashboard (<b>Transactions → Import</b>) to map columns manually."
            );

            return;
        }

        $result = $this->importer->validate($parsed['rows'], $mapping, 'no', $user);

        $validCount = count($result['valid']);
        $dupCount = count($result['duplicates']);
        $invalidCount = count($result['invalid']);

        if ($validCount === 0 && $dupCount === 0) {
            $sampleErrors = array_map(
                fn (array $row): string => "• Line {$row['line']}: ".e(implode(' ', $row['errors'])),
                array_slice($result['invalid'], 0, 3)
            );

            $this->bot->sendMessage(
                $chatId,
                "⚠️ <b>No Importable Rows Found</b>\n\nFile: <code>".e($filename)."</code>\nSkipped rows: <b>{$invalidCount}</b>\n\n".implode("\n", $sampleErrors)
            );

            return;
        }

        $key = 'tg-'.Str::random(24);
        Storage::disk('local')->put(
            self::IMPORT_STAGING_DIR.'/'.$key.'.json',
            json_encode([
                'user_id' => $user->id,
                'filename' => $filename,
                'headers' => $parsed['headers'],
                'rows' => $parsed['rows'],
                'mapping' => $mapping,
                'signed_amount' => 'no',
            ], JSON_THROW_ON_ERROR)
        );

        $mappingSummary = [];
        foreach (['date' => 'Date', 'amount' => 'Amount', 'type' => 'Type', 'currency' => 'Currency', 'category' => 'Category', 'note' => 'Note'] as $field => $label) {
            if (! empty($mapping[$field])) {
                $mappingSummary[] = "{$label}: <code>".e((string) $mapping[$field]).'</code>';
            }
        }

        $lines = [
            '📄 <b>CSV Import Preview</b>',
            '',
            'File: <code>'.e($filename).'</code>',
            'Rows read: <b>'.count($parsed['rows']).'</b>',
            "✅ Ready to import: <b>{$validCount}</b>",
        ];

        if ($dupCount > 0) {
            $lines[] = "🔁 Duplicates detected: <b>{$dupCount}</b> <i>(skipped by default)</i>";
        }

        if ($invalidCount > 0) {
            $lines[] = "⚠️ Skipped / invalid: <b>{$invalidCount}</b>";
        }

        $lines[] = '';
        $lines[] = '<b>Detected columns:</b>';
        $lines[] = implode(' · ', $mappingSummary);

        $sampleRows = array_slice($result['valid'] !== [] ? $result['valid'] : $result['duplicates'], 0, 4);
        if ($sampleRows !== []) {
            $lines[] = '';
            $lines[] = '<b>Sample rows:</b>';
            foreach ($sampleRows as $entry) {
                $attrs = $entry['attributes'];
                $icon = $attrs['type'] === TransactionType::Income ? '🟢' : '🔴';
                $amt = number_format((float) $attrs['amount'], 2);
                $dateStr = $attrs['occurred_at'] instanceof CarbonImmutable
                    ? $attrs['occurred_at']->format('Y-m-d')
                    : substr((string) $attrs['occurred_at'], 0, 10);
                $label = $attrs['note'] ?: ($attrs['category'] ?: 'Transaction');
                $lines[] = "• {$icon} <b>{$amt} {$attrs['currency']}</b> — ".e(Str::limit((string) $label, 30))." ({$dateStr})";
            }
        }

        $keyboard = [];
        if ($validCount > 0) {
            $keyboard[] = [
                ['text' => "✅ Import {$validCount} row".($validCount === 1 ? '' : 's'), 'callback_data' => "import:confirm:{$key}"],
            ];
        }
        if ($dupCount > 0) {
            $allCount = $validCount + $dupCount;
            $keyboard[] = [
                ['text' => "🔁 Import all incl. duplicates ({$allCount})", 'callback_data' => "import:force:{$key}"],
            ];
        }
        $keyboard[] = [
            ['text' => '❌ Cancel', 'callback_data' => "import:cancel:{$key}"],
        ];

        $this->bot->sendMessage(
            $chatId,
            implode("\n", $lines),
            ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]
        );
    }

    /**
     * Handle inline confirmation or cancellation of a staged Telegram CSV import.
     */
    protected function handleImportCallback(
        string $callbackId,
        int|string|null $chatId,
        ?int $messageId,
        string $action,
        string $key,
    ): void {
        $user = $chatId !== null ? User::where('telegram_chat_id', $chatId)->first() : null;
        if (! $user) {
            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'Please link your Finance Tracker account first.', true);
            }

            return;
        }

        $stagedPath = self::IMPORT_STAGING_DIR.'/'.$key.'.json';
        $raw = Storage::disk('local')->get($stagedPath);

        if ($raw === null || $raw === '') {
            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'This import preview has expired. Please upload the CSV again.', true);
            }

            return;
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            Storage::disk('local')->delete($stagedPath);
            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'Import data was corrupted. Please upload again.', true);
            }

            return;
        }

        if ((int) ($payload['user_id'] ?? 0) !== $user->id) {
            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'You are not authorized to confirm this import.', true);
            }

            return;
        }

        $filename = (string) ($payload['filename'] ?? 'statement.csv');

        if ($action === 'cancel') {
            Storage::disk('local')->delete($stagedPath);

            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'Import cancelled.');
            }

            if ($chatId !== null && $messageId !== null) {
                $this->bot->editMessageText(
                    $chatId,
                    $messageId,
                    '❌ <b>CSV Import Cancelled</b>'."\n\nNo transactions were added from <code>".e($filename).'</code>.',
                    ['reply_markup' => json_encode(['inline_keyboard' => []])]
                );
            }

            return;
        }

        $result = $this->importer->validate(
            $payload['rows'],
            $payload['mapping'],
            (string) ($payload['signed_amount'] ?? 'no'),
            $user,
        );

        $skipDuplicates = $action === 'confirm';
        $rowsToImport = $skipDuplicates
            ? $result['valid']
            : array_merge($result['valid'], $result['duplicates']);

        // Remove staging file before committing so repeated taps cannot double-import.
        Storage::disk('local')->delete($stagedPath);

        if ($rowsToImport === []) {
            if ($callbackId !== '') {
                $this->bot->answerCallbackQuery($callbackId, 'No new rows left to import.', true);
            }

            if ($chatId !== null && $messageId !== null) {
                $this->bot->editMessageText(
                    $chatId,
                    $messageId,
                    'ℹ️ <b>Nothing Imported</b>'."\n\nAll rows in <code>".e($filename).'</code> were already imported or invalid.',
                    ['reply_markup' => json_encode(['inline_keyboard' => []])]
                );
            }

            return;
        }

        $batch = $this->importer->import(
            $user,
            array_column($rowsToImport, 'attributes'),
            $filename,
        );

        $skippedInvalid = count($result['invalid']);
        $skippedDups = $skipDuplicates ? count($result['duplicates']) : 0;

        $summaryLines = [
            '🎉 <b>CSV Import Complete!</b>',
            '',
            'File: <code>'.e($batch->filename).'</code>',
            "✅ Imported: <b>{$batch->imported_count}</b> transaction".($batch->imported_count === 1 ? '' : 's'),
        ];

        if ($skippedDups > 0) {
            $summaryLines[] = "🔁 Duplicates skipped: <b>{$skippedDups}</b>";
        }
        if ($skippedInvalid > 0) {
            $summaryLines[] = "⚠️ Invalid rows skipped: <b>{$skippedInvalid}</b>";
        }

        $summaryLines[] = '';
        $summaryLines[] = '<i>Saved as Import Batch #'.$batch->id.'. You can review or undo this import anytime on the web under Past imports.</i>';

        if ($callbackId !== '') {
            $this->bot->answerCallbackQuery($callbackId, "Imported {$batch->imported_count} transaction(s)!");
        }

        if ($chatId !== null && $messageId !== null) {
            $this->bot->editMessageText(
                $chatId,
                $messageId,
                implode("\n", $summaryLines),
                ['reply_markup' => json_encode(['inline_keyboard' => []])]
            );
        }
    }
}
