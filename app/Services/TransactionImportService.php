<?php

namespace App\Services;

use App\Actions\ConvertTransactionAmount;
use App\Enums\TransactionType;
use App\Models\TransactionImport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Turns mapped CSV rows into transactions.
 *
 * Two properties matter here and are easy to get wrong:
 *
 * 1. Every row goes through ConvertTransactionAmount, the same action the
 *    create form uses. Writing base_amount directly would leave imported rows
 *    without a base snapshot, which silently excludes them from all totals.
 *
 * 2. A bad row never aborts the batch. Exports routinely contain footer lines,
 *    totals rows, or blank records, and rejecting a 2,000-row file because of
 *    three stray lines is not useful behaviour.
 */
class TransactionImportService
{
    /**
     * Date formats tried in order when parsing a date cell.
     *
     * Exports vary widely and cannot be told apart by shape alone, so a
     * permissive list is tried before declaring a cell unreadable.
     *
     * @var array<int, string>
     */
    private const DATE_FORMATS = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'Y/m/d H:i:s',
        'Y/m/d',
        'd/m/Y H:i:s',
        'd/m/Y',
        'm/d/Y H:i:s',
        'm/d/Y',
        'd.m.Y H:i',
        'd.m.Y',
        'Ymd',
        'Y年n月j日 H:i',
        'Y年n月j日',
    ];

    public function __construct(private readonly ConvertTransactionAmount $convert) {}

    /**
     * Guess which column plays which part, from the header names.
     *
     * Exports from payment apps and banks are consistent enough that a first
     * guess is usually right, which saves mapping six columns by hand every
     * month. A guess is only a starting point: the preview is shown before
     * anything is written, and the mapping can be corrected.
     *
     * @param  array<int, string>  $headers
     * @return array<string, string|null>
     */
    public function guessMapping(array $headers): array
    {
        $aliases = [
            'date' => ['date', 'time', 'timestamp', 'transaction date', 'occurred at', '日期', '交易时间', '交易日期'],
            'amount' => ['amount', 'value', 'sum', 'total', 'money', '金额', '交易金额', '发生额'],
            'type' => ['type', 'direction', 'kind', 'dr/cr', 'debit/credit', '类型', '收/支', '收支'],
            'currency' => ['currency', 'ccy', '货币', '币种'],
            'category' => ['category', 'cat', 'tag', '分类', '类别'],
            'note' => ['note', 'notes', 'memo', 'description', 'details', 'merchant', '备注', '说明', '交易说明'],
        ];

        $mapping = [];

        foreach ($aliases as $field => $candidates) {
            $mapping[$field] = $this->matchHeader($headers, $candidates);
        }

        // A file with income and expense columns needs no signed amount.
        return $mapping;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $candidates
     */
    private function matchHeader(array $headers, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            foreach ($headers as $header) {
                if ($this->normalise($header) === $this->normalise($candidate)) {
                    return $header;
                }
            }
        }

        foreach ($candidates as $candidate) {
            foreach ($headers as $header) {
                if (str_contains($this->normalise($header), $this->normalise($candidate))) {
                    return $header;
                }
            }
        }

        return null;
    }

    private function normalise(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * Validate mapped rows without saving anything.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, string|null>  $mapping  field name => header label
     * @return array{valid: array<int, array<string, mixed>>, invalid: array<int, array{line: int, errors: array<int, string>}>}
     */
    public function validate(array $rows, array $mapping, string $signedAmount = 'no'): array
    {
        $valid = [];
        $invalid = [];

        foreach ($rows as $index => $row) {
            // Line 1 is the header, so data rows start at line 2.
            $line = $index + 2;

            $attributes = $this->attributesFrom($row, $mapping, $signedAmount);

            if ($attributes === null) {
                $invalid[] = [
                    'line' => $line,
                    'errors' => $this->explainSkip($row, $mapping, $signedAmount),
                ];

                continue;
            }

            $errors = $this->validationErrors($attributes, $signedAmount);

            if ($errors !== []) {
                $invalid[] = ['line' => $line, 'errors' => $errors];

                continue;
            }

            $valid[] = ['line' => $line, 'attributes' => $attributes];
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * Persist validated rows as one import batch.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function import(User $user, array $rows, string $filename): TransactionImport
    {
        $batch = TransactionImport::create([
            'user_id' => $user->id,
            'filename' => $filename,
            'imported_count' => count($rows),
        ]);

        foreach ($rows as $row) {
            $user->transactions()->create([
                ...$row,
                'import_id' => $batch->id,
                ...$this->convert->apply($row),
            ]);
        }

        return $batch;
    }

    /**
     * Build transaction attributes from one mapped row.
     *
     * Returns null when the row is export furniture rather than a transaction,
     * which is the common case for the trailing summary lines banks append.
     *
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $mapping
     * @return array<string, mixed>|null
     */
    private function attributesFrom(array $row, array $mapping, string $signedAmount): ?array
    {
        $date = $this->cell($row, $mapping, 'date');

        if ($date === null) {
            return null;
        }

        $occurredAt = $this->parseDate($date);

        if ($occurredAt === null) {
            return null;
        }

        $amountCell = $this->cell($row, $mapping, 'amount');
        $amount = $this->parseAmount($amountCell);

        if ($amount === null) {
            return null;
        }

        $type = $this->resolveType($amount, $row, $mapping, $signedAmount);

        if ($type === null) {
            return null;
        }

        // Amounts are stored unsigned; direction is carried by the type, which
        // is why a negative value in a signed column flips the type rather
        // than producing a negative amount.
        return [
            'type' => $type,
            'amount' => abs($amount),
            'currency' => $this->resolveCurrency($row, $mapping),
            'occurred_at' => $occurredAt,
            'category' => $this->cell($row, $mapping, 'category') ?: null,
            'note' => $this->cell($row, $mapping, 'note') ?: null,
        ];
    }

    /**
     * Why a row was skipped, phrased for a person looking at their own file.
     *
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $mapping
     * @return array<int, string>
     */
    private function explainSkip(array $row, array $mapping, string $signedAmount): array
    {
        $date = $this->cell($row, $mapping, 'date');
        $amountCell = $this->cell($row, $mapping, 'amount');

        if ($date === null && $amountCell === null) {
            return ['Skipped: no date and no amount, so this looks like a total or header line.'];
        }

        if ($date !== null && $this->parseDate($date) === null) {
            return ['Skipped: "'.$date.'" is not a date this importer recognises.'];
        }

        if ($amountCell === null) {
            return ['Skipped: the amount is empty.'];
        }

        if ($this->parseAmount($amountCell) === null) {
            return ['Skipped: "'.$amountCell.'" is not an amount.'];
        }

        if ($this->parseAmount($amountCell) === 0.0 && $signedAmount === 'no') {
            return ['Skipped: the amount is zero and there is no type column to say what it was.'];
        }

        return ['Skipped: the type could not be worked out from this row.'];
    }

    /**
     * Apply the same rules the create form uses, so an imported transaction is
     * indistinguishable from a typed one.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    private function validationErrors(array $attributes, string $signedAmount): array
    {
        $errors = Validator::make($attributes, [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
        ])->errors()->all();

        if ($signedAmount === 'yes' && $attributes['amount'] === 0.0) {
            $errors[] = 'The amount is zero.';
        }

        return $errors;
    }

    /**
     * Read the cell for a mapped field.
     *
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $mapping
     */
    private function cell(array $row, array $mapping, string $field): ?string
    {
        $header = $mapping[$field] ?? null;

        if ($header === null || $header === '') {
            return null;
        }

        $value = trim((string) ($row[$header] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Parse an amount, tolerating currency symbols and thousands separators.
     *
     * Only genuine currency symbols are removed. Stripping every non-numeric
     * character would turn a trailing summary line such as "Total: 25.00"
     * into a valid amount and import it as though it were a transaction.
     */
    private function parseAmount(?string $cell): ?float
    {
        if ($cell === null) {
            return null;
        }

        // \p{Sc} covers the currency symbols; 元 and 円 are the units some
        // exports append to the figure rather than prefix it with.
        $cleaned = preg_replace('/[\p{Sc}\s元円]/u', '', $cell) ?? '';

        // Grouping separators are dropped: the decimal separator is a dot in
        // every export format this handles, so removing commas is safe.
        $cleaned = str_replace(',', '', $cleaned);

        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        foreach (self::DATE_FORMATS as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $value);
            } catch (Throwable) {
                // Carbon throws when the value cannot fill the format at all.
                continue;
            }

            // The round trip confirms this format genuinely describes the
            // value. Without it a looser pattern could match part of a longer
            // value and silently drop the rest of the date.
            if ($parsed === null || $parsed->format($format) !== $value) {
                continue;
            }

            // A date-only format leaves the time of day as it is now, which
            // would scatter imports across whatever hour the upload happened
            // to be. Midnight keeps same-day rows in a stable order.
            return preg_match('/[Hg]/', $format) === 1
                ? $parsed
                : $parsed->setTime(0, 0);
        }

        return null;
    }

    /**
     * Decide whether a row is income or expense.
     *
     * A dedicated type column wins when the export has one. Otherwise the sign
     * of the amount carries the direction, which is how most bank and payment
     * app exports encode it.
     *
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $mapping
     */
    private function resolveType(float $amount, array $row, array $mapping, string $signedAmount): ?TransactionType
    {
        $typeCell = $this->cell($row, $mapping, 'type');

        if ($typeCell !== null) {
            $normalized = strtolower($typeCell);

            return match (true) {
                in_array($normalized, ['income', 'in', 'credit', 'received', 'revenue'], true) => TransactionType::Income,
                in_array($normalized, ['expense', 'ex', 'debit', 'spent', 'payment'], true) => TransactionType::Expense,
                default => null,
            };
        }

        if ($signedAmount === 'no' && $amount === 0.0) {
            return null;
        }

        // Without a type column the sign is the only direction signal: a
        // negative amount is money leaving, a positive amount money arriving.
        return $amount < 0 ? TransactionType::Expense : TransactionType::Income;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $mapping
     */
    private function resolveCurrency(array $row, array $mapping): string
    {
        $currency = $this->cell($row, $mapping, 'currency');

        if ($currency === null) {
            return strtoupper((string) config('finance.base_currency'));
        }

        return strtoupper(preg_replace('/[^A-Za-z]/', '', $currency) ?? '');
    }
}
