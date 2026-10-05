<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FrankfurterRateProvider
{
    private const ENDPOINT = 'https://api.frankfurter.dev/v2/rates';

    private const TIMEOUT_SECONDS = 10;

    /**
     * Resolve the rate to convert one unit of $currency into the base currency.
     *
     * Always requests the currency as the base rather than inverting, because
     * the provider rounds to five decimals. IDR is a large number, so
     * CNY -> IDR stays precise while IDR -> CNY loses roughly 1.2%.
     */
    public function fetchRate(string $currency, string $baseCurrency, CarbonImmutable $onDate): ?ExchangeRate
    {
        if ($currency === $baseCurrency) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->get(self::ENDPOINT, [
                    'base' => $currency,
                    'quotes' => $baseCurrency,
                    'date' => $onDate->toDateString(),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Exchange rate provider unreachable.', [
                'currency' => $currency,
                'base_currency' => $baseCurrency,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Exchange rate provider returned an error.', [
                'status' => $response->status(),
                'currency' => $currency,
            ]);

            return null;
        }

        $rate = $response->json('0.rate');

        if ($rate === null || ! is_numeric($rate) || (float) $rate <= 0) {
            return null;
        }

        return ExchangeRate::updateOrCreate(
            [
                'base_currency' => strtoupper($currency),
                'quote_currency' => strtoupper($baseCurrency),
                'rate_date' => $onDate->toDateString(),
            ],
            ['rate' => (float) $rate],
        );
    }
}
