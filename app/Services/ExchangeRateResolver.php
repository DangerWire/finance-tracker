<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the exchange rate to use when storing a transaction.
 *
 * Rates are cached per pair and date so that creating many transactions on the
 * same day costs a single API call. The rate applied to a transaction is
 * recorded on the transaction itself and never recomputed, so historical
 * totals stay stable when rates move.
 */
class ExchangeRateResolver
{
    /**
     * The maximum age of a cached rate before a fresh one is requested.
     */
    private const MAX_CACHE_AGE_DAYS = 7;

    public function __construct(private readonly FrankfurterRateProvider $provider) {}

    /**
     * Resolve the rate to convert $currency into $baseCurrency on $onDate.
     */
    public function resolve(string $currency, string $baseCurrency, CarbonImmutable $onDate): ?ExchangeRate
    {
        $currency = strtoupper($currency);
        $baseCurrency = strtoupper($baseCurrency);

        if ($currency === $baseCurrency) {
            return null;
        }

        $cached = $this->findCached($currency, $baseCurrency, $onDate);

        if ($cached !== null) {
            return $cached;
        }

        $rate = $this->provider->fetchRate($currency, $baseCurrency, $onDate);

        if ($rate === null) {
            Log::warning('Unable to resolve exchange rate; falling back to most recent cached rate.', [
                'currency' => $currency,
                'base_currency' => $baseCurrency,
                'date' => $onDate->toDateString(),
            ]);

            return $this->findMostRecent($currency, $baseCurrency);
        }

        return $rate;
    }

    /**
     * Find a cached rate for the exact date or a recent enough earlier date.
     */
    private function findCached(string $currency, string $baseCurrency, CarbonImmutable $onDate): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('base_currency', $currency)
            ->where('quote_currency', $baseCurrency)
            ->where('rate_date', '>=', $onDate->subDays(self::MAX_CACHE_AGE_DAYS)->startOfDay())
            ->where('rate_date', '<=', $onDate->endOfDay())
            ->orderByDesc('rate_date')
            ->first();
    }

    /**
     * Find the most recent cached rate regardless of age.
     */
    private function findMostRecent(string $currency, string $baseCurrency): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('base_currency', $currency)
            ->where('quote_currency', $baseCurrency)
            ->orderByDesc('rate_date')
            ->first();
    }
}
