<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionExportController extends Controller
{
    /**
     * Stream filtered transactions as a UTF-8 CSV download with formula-injection protection.
     */
    public function csv(Request $request): StreamedResponse
    {
        $filters = $this->validateFilters($request);
        [$from, $to] = $this->periodFrom($filters);

        $query = $this->applyFilters($request->user()->transactions()->getQuery(), $filters, $from, $to)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $filename = 'finance-tracker-statement-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            // UTF-8 BOM ensures Excel and spreadsheet apps decode accented/CJK text cleanly.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Date',
                'Type',
                'Category',
                'Note',
                'Amount',
                'Currency',
                'Base Amount',
                'Base Currency',
                'Applied Rate',
            ]);

            $query->chunk(250, function ($transactions) use ($output): void {
                foreach ($transactions as $transaction) {
                    /** @var Transaction $transaction */
                    fputcsv($output, [
                        $transaction->occurred_at?->format('Y-m-d H:i:s') ?? '',
                        $this->safeCsvCell($transaction->type->label()),
                        $this->safeCsvCell((string) ($transaction->category ?? '')),
                        $this->safeCsvCell((string) ($transaction->note ?? '')),
                        number_format((float) $transaction->amount, 2, '.', ''),
                        $this->safeCsvCell((string) $transaction->currency),
                        $transaction->base_amount !== null ? number_format((float) $transaction->base_amount, 4, '.', '') : '',
                        $this->safeCsvCell((string) ($transaction->base_currency ?? '')),
                        $transaction->applied_rate !== null ? (string) $transaction->applied_rate : '',
                    ]);
                }
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Render a printable, A4-formatted financial statement view (Save as PDF via browser print).
     */
    public function statement(Request $request): View
    {
        $filters = $this->validateFilters($request);
        [$from, $to] = $this->periodFrom($filters);

        $user = $request->user();
        $baseCurrency = strtoupper((string) ($user->currency ?? config('finance.base_currency', 'CNY')));

        $transactions = $this->applyFilters($user->transactions()->with('splits')->getQuery(), $filters, $from, $to)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        $summary = $this->applyFilters($user->transactions()->getQuery(), $filters, $from, $to)
            ->whereNotNull('base_amount')
            ->where('base_currency', $baseCurrency)
            ->selectRaw('type, SUM(base_amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = round((float) ($summary[TransactionType::Income->value] ?? 0), 4);
        $expenses = round((float) ($summary[TransactionType::Expense->value] ?? 0), 4);
        $balance = round($income - $expenses, 4);

        $unconvertedCount = $this->applyFilters($user->transactions()->getQuery(), $filters, $from, $to)
            ->where(fn ($q) => $q
                ->whereNull('base_amount')
                ->orWhere('base_currency', '!=', $baseCurrency))
            ->count();

        return view('transactions.statement', [
            'user' => $user,
            'transactions' => $transactions,
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'baseCurrency' => $baseCurrency,
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $balance,
            'unconvertedCount' => $unconvertedCount,
            'generatedAt' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Validate export query filters identically to the ledger index.
     *
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'type' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function periodFrom(array $filters): array
    {
        if (! empty($filters['date'])) {
            $day = CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['date'])->startOfDay();

            return [$day, $day->endOfDay()];
        }

        $from = empty($filters['from'])
            ? null
            : CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['from'])->startOfDay();
        $to = empty($filters['to'])
            ? null
            : CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['to'])->endOfDay();

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, ?CarbonImmutable $from, ?CarbonImmutable $to): Builder
    {
        return $query
            ->when($filters['type'] ?? null, fn (Builder $q) => $q->where('type', $filters['type']))
            ->when($filters['category'] ?? null, fn (Builder $q) => $q->where('category', $filters['category']))
            ->when($from !== null, fn (Builder $q) => $q->where('occurred_at', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->where('occurred_at', '<=', $to));
    }

    /**
     * Neutralize spreadsheet formula injection by quoting cells starting with trigger characters.
     */
    private function safeCsvCell(string $value): string
    {
        if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            return "'".$value;
        }

        return $value;
    }
}
