<?php

namespace App\Actions;

use App\Models\Transaction;
use App\Services\ExchangeRateResolver;
use Carbon\CarbonImmutable;

/**
 * Converts a transaction amount into the base currency and records the rate used.
 *
 * Conversion happens once, when the transaction is stored, so that historical
 * totals never shift when exchange rates move. The original amount is never
 * modified.
 */
class ConvertTransactionAmount
{
    public function __construct(private readonly ExchangeRateResolver $rates) {}

    /**
     * Apply the base currency snapshot to the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function apply(array $attributes): array
    {
        $baseCurrency = config('finance.base_currency');
        $amount = (float) $attributes['amount'];
        $occurredAt = CarbonImmutable::parse($attributes['occurred_at']);

        if (strtoupper((string) $attributes['currency']) === strtoupper($baseCurrency)) {
            $attributes['base_currency'] = strtoupper($baseCurrency);
            $attributes['base_amount'] = round($amount, 4);
            $attributes['applied_rate'] = 1;

            return $attributes;
        }

        $rate = $this->rates->resolve((string) $attributes['currency'], $baseCurrency, $occurredAt);

        if ($rate === null) {
            // Without a rate the transaction is still valid; it just cannot be
            // included in base currency totals until one is backfilled.
            $attributes['base_currency'] = null;
            $attributes['base_amount'] = null;
            $attributes['applied_rate'] = null;

            return $attributes;
        }

        $attributes['base_currency'] = $rate->quote_currency;
        $attributes['base_amount'] = round($amount * (float) $rate->rate, 4);
        $attributes['applied_rate'] = $rate->rate;

        return $attributes;
    }

    /**
     * Recompute the base snapshot for an existing transaction.
     */
    public function recalculate(Transaction $transaction): Transaction
    {
        $transaction->fill($this->apply($transaction->only([
            'amount',
            'currency',
            'occurred_at',
        ])));

        return $transaction;
    }
}
