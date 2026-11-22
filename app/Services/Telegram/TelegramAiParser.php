<?php

namespace App\Services\Telegram;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;
use Throwable;

class TelegramAiParser
{
    /**
     * Parse natural language text into transaction components.
     *
     * @return array{type: string, amount: float, category: string, note: string, method: string}|null
     */
    public function parse(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (filled(config('insights.api_key'))) {
            try {
                $result = $this->parseWithGemini($text);
                if ($result !== null) {
                    return $result;
                }
            } catch (Throwable $e) {
                Log::warning('TelegramAiParser: Gemini API failed, falling back to heuristic', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->parseHeuristic($text);
    }

    /**
     * Use Gemini (via Prism) to extract structured financial transaction data.
     *
     * @return array{type: string, amount: float, category: string, note: string, method: string}|null
     */
    protected function parseWithGemini(string $text): ?array
    {
        $schema = new ObjectSchema(
            name: 'transaction_extract',
            description: 'Extracted transaction details from user message',
            properties: [
                'type' => new EnumSchema(
                    name: 'type',
                    description: 'Whether this transaction is an expense or income',
                    options: ['expense', 'income'],
                ),
                'amount' => new NumberSchema(
                    name: 'amount',
                    description: 'The numeric money amount (e.g. 35000 for 35k or 35.000)',
                ),
                'category' => new StringSchema(
                    name: 'category',
                    description: 'Standard financial category like Food & Dining, Coffee, Groceries, Transportation, Salary, Freelance, Shopping, Utilities, Entertainment, General',
                ),
                'note' => new StringSchema(
                    name: 'note',
                    description: 'Brief description or memo of what was purchased or received',
                ),
            ],
            requiredFields: ['type', 'amount', 'category', 'note'],
        );

        $systemPrompt = implode("\n", [
            'You are an expert financial assistant. Extract transaction details from user input in English or Indonesian.',
            'Handle colloquial representations:',
            '- "35k", "35rb", "35 ribu" => 35000',
            '- "1.5jt", "1.5 juta", "1.5m" => 1500000',
            '- "500", "50" => as numeric',
            '- Default type is expense unless keywords indicate incoming money (gaji, salary, earned, bonus, received, dapat).',
        ]);

        $response = Prism::structured()
            ->using(Provider::Gemini, config('insights.model', 'gemini-2.5-flash'), array_filter(['api_key' => config('insights.api_key')]))
            ->withSchema($schema)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt("User message: \"{$text}\"")
            ->asStructured();

        $decoded = $response->structured ?? [];

        if (empty($decoded['amount']) || (float) $decoded['amount'] <= 0) {
            return null;
        }

        return [
            'type' => ($decoded['type'] ?? 'expense') === 'income' ? TransactionType::Income->value : TransactionType::Expense->value,
            'amount' => (float) $decoded['amount'],
            'category' => (string) ($decoded['category'] ?? 'General'),
            'note' => (string) ($decoded['note'] ?? $text),
            'method' => 'ai',
        ];
    }

    /**
     * Extract transaction details from a receipt photo using Gemini Vision.
     *
     * @return array{type: string, amount: float, category: string, note: string, occurred_at: CarbonImmutable, method: string}|null
     */
    public function parseReceiptImage(string $rawBytes, string $mimeType = 'image/jpeg', ?string $caption = null): ?array
    {
        if (! filled(config('insights.api_key')) || $rawBytes === '') {
            return null;
        }

        try {
            $schema = new ObjectSchema(
                name: 'receipt_extract',
                description: 'Extracted transaction data from a receipt image',
                properties: [
                    'type' => new EnumSchema(
                        name: 'type',
                        description: 'Whether this transaction is an expense or income',
                        options: ['expense', 'income'],
                    ),
                    'amount' => new NumberSchema(
                        name: 'amount',
                        description: 'Final total amount paid or received shown on the receipt',
                    ),
                    'category' => new StringSchema(
                        name: 'category',
                        description: 'Financial category such as Food & Dining, Coffee, Groceries, Transportation, Shopping, Utilities, Entertainment, General',
                    ),
                    'note' => new StringSchema(
                        name: 'note',
                        description: 'Merchant name plus a brief summary of purchased items',
                    ),
                    'occurred_date' => new StringSchema(
                        name: 'occurred_date',
                        description: 'Receipt date in YYYY-MM-DD format, or empty string if unclear',
                    ),
                ],
                requiredFields: ['type', 'amount', 'category', 'note', 'occurred_date'],
            );

            $systemPrompt = implode("\n", [
                'You are a careful personal finance assistant extracting receipt details from a photo.',
                '- Read the final total (Grand Total / Total / Bayar) paid, not subtotals or change.',
                '- Provide a clean merchant + brief summary for note.',
                '- Return occurred_date as YYYY-MM-DD if clearly legible on the receipt, or "" if not legible.',
            ]);

            $prompt = $caption !== null && trim($caption) !== ''
                ? "Extract the financial transaction from this receipt photo. User caption context: \"{$caption}\""
                : 'Extract the financial transaction from this receipt photo.';

            $response = Prism::structured()
                ->using(Provider::Gemini, config('insights.model', 'gemini-2.5-flash'), array_filter(['api_key' => config('insights.api_key')]))
                ->withSchema($schema)
                ->withSystemPrompt($systemPrompt)
                ->withPrompt($prompt, [Image::fromBase64(base64_encode($rawBytes), $mimeType)])
                ->asStructured();

            $decoded = $response->structured ?? [];

            if (empty($decoded['amount']) || (float) $decoded['amount'] <= 0) {
                return null;
            }

            $occurredAt = CarbonImmutable::now();
            $dateStr = trim((string) ($decoded['occurred_date'] ?? ''));

            if ($dateStr !== '') {
                try {
                    $parsedDate = CarbonImmutable::createFromFormat('Y-m-d', $dateStr);
                    if ($parsedDate !== false && $parsedDate->format('Y-m-d') === $dateStr) {
                        $occurredAt = $parsedDate->setTime($occurredAt->hour, $occurredAt->minute, $occurredAt->second);
                    }
                } catch (Throwable) {
                    // Fall back to current timestamp when receipt date is unparseable.
                }
            }

            return [
                'type' => ($decoded['type'] ?? 'expense') === 'income' ? TransactionType::Income->value : TransactionType::Expense->value,
                'amount' => (float) $decoded['amount'],
                'category' => trim((string) ($decoded['category'] ?? '')) ?: 'General',
                'note' => trim((string) ($decoded['note'] ?? '')) ?: 'Scanned receipt',
                'occurred_at' => $occurredAt,
                'method' => 'vision',
            ];
        } catch (Throwable $e) {
            Log::warning('TelegramAiParser: Gemini Vision receipt scan failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fast, reliable heuristic parser for Indonesian and English phrasing.
     *
     * @return array{type: string, amount: float, category: string, note: string, method: string}|null
     */
    public function parseHeuristic(string $text): ?array
    {
        $normalized = strtolower($text);

        // 1. Determine Type
        $isIncome = (bool) preg_match('/\b(income|gaji|gajian|received|earned|dapat|terima|bonus|pemasukan|transfer masuk)\b/i', $normalized);
        $type = $isIncome ? TransactionType::Income->value : TransactionType::Expense->value;

        // 2. Extract Amount
        $amount = $this->extractAmount($text);

        if ($amount === null || $amount <= 0) {
            return null;
        }

        // 3. Category Categorization
        $category = 'General';
        if (preg_match('/\b(kopi|coffee|starbucks|cafe|kafe)\b/i', $normalized)) {
            $category = 'Coffee';
        } elseif (preg_match('/\b(makan|lunch|dinner|breakfast|food|nasi|resto|bakso|ayam|mie|sate)\b/i', $normalized)) {
            $category = 'Food & Dining';
        } elseif (preg_match('/\b(grab|gojek|maxim|uber|taxi|taksi|bensin|fuel|pertalite|pertamax|parkir|toll|tol|bus|kereta|train)\b/i', $normalized)) {
            $category = 'Transportation';
        } elseif (preg_match('/\b(listrik|pln|pdam|air|wifi|internet|indihome|pulsa|kuota|bpjs)\b/i', $normalized)) {
            $category = 'Utilities';
        } elseif (preg_match('/\b(baju|clothes|sepatu|shoes|belanja|shopping|tokopedia|shopee|lazada)\b/i', $normalized)) {
            $category = 'Shopping';
        } elseif (preg_match('/\b(nonton|bioskop|cinema|netflix|game|steam|spotify)\b/i', $normalized)) {
            $category = 'Entertainment';
        } elseif ($isIncome) {
            $category = preg_match('/\b(gaji|salary)\b/i', $normalized) ? 'Salary' : 'Income';
        }

        // 4. Construct Note
        // Remove common trigger words and amount digits to leave a meaningful note
        $cleaned = preg_replace('/\b(spent|buy|bought|beli|bayar|keluar|income|gaji|gajian|dapat|terima|rp|idr|\$|cny|rmb)\b/i', '', $text);
        $cleaned = preg_replace('/(\d+(?:[.,]\d+)?\s*(k|rb|ribu|jt|juta|mio|m)?)/i', '', (string) $cleaned);
        $cleaned = trim((string) preg_replace('/\s+/', ' ', (string) $cleaned));

        $note = $cleaned !== '' ? $cleaned : $text;

        return [
            'type' => $type,
            'amount' => (float) $amount,
            'category' => $category,
            'note' => $note,
            'method' => 'heuristic',
        ];
    }

    /**
     * Extract a numeric amount from text supporting Indonesian and standard decimal formats.
     */
    public function extractAmount(string $text): ?float
    {
        $normalized = strtolower(trim($text));

        // 1. Multipliers (k, rb, ribu, jt, juta, m, mio)
        if (preg_match('/(?:rp|idr|\$|cny|rmb)?\s*(\d+(?:[.,]\d+)?)\s*(k|rb|ribu|jt|juta|mio|m)\b/i', $normalized, $m)) {
            $val = (float) str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);

            if (in_array($unit, ['k', 'rb', 'ribu'], true)) {
                return $val * 1000;
            }
            if (in_array($unit, ['jt', 'juta', 'mio', 'm'], true)) {
                return $val * 1000000;
            }
        }

        // 2. Thousand-separated with dots e.g. 50.000 or 1.500.000
        if (preg_match('/(?:rp|idr|\$|cny|rmb)?\s*(\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?)/i', $normalized, $m)) {
            $clean = str_replace('.', '', $m[1]);
            $clean = str_replace(',', '.', $clean);

            return (float) $clean;
        }

        // 3. Thousand-separated with commas e.g. 50,000 or 1,500,000.50
        if (preg_match('/(?:rp|idr|\$|cny|rmb)?\s*(\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?)/i', $normalized, $m)) {
            $clean = str_replace(',', '', $m[1]);

            return (float) $clean;
        }

        // 4. Standard decimal / plain number e.g. 45.50 or 100
        if (preg_match('/(?:rp|idr|\$|cny|rmb)?\s*(\d+(?:\.\d+)?)/i', $normalized, $m)) {
            $val = (float) $m[1];
            if ($val > 0) {
                return $val;
            }
        }

        return null;
    }
}
