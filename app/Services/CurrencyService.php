<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class CurrencyService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const REQUEST_TIMEOUT = 10; // seconds
    private const MAX_RETRIES = 3;

    private array $sources = [
        'exchangerate-api' => 'https://api.exchangerate-api.com/v4/latest/BRL',
        'fixer' => 'https://api.fixer.io/latest',
        'coingecko' => 'https://api.coingecko.com/api/v3/simple/price',
    ];

    public function getCurrencyRates(array $currencies = ['USD', 'EUR', 'BTC']): array
    {
        $cacheKey = 'currency_rates_' . implode('_', $currencies);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($currencies) {
            return $this->fetchCurrencyRates($currencies);
        });
    }

    private function fetchCurrencyRates(array $currencies): array
    {
        $rates = [];
        $cryptoCurrencies = ['BTC', 'ETH'];
        $fiatCurrencies = array_diff($currencies, $cryptoCurrencies);

        // Fetch fiat currencies
        if (!empty($fiatCurrencies)) {
            $fiatRates = $this->fetchFiatRates($fiatCurrencies);
            $rates = array_merge($rates, $fiatRates);
        }

        // Fetch crypto currencies
        if (!empty(array_intersect($currencies, $cryptoCurrencies))) {
            $cryptoRates = $this->fetchCryptoRates(array_intersect($currencies, $cryptoCurrencies));
            $rates = array_merge($rates, $cryptoRates);
        }

        return $rates;
    }

    private function fetchFiatRates(array $currencies): array
    {
        $attempts = 0;

        while ($attempts < self::MAX_RETRIES) {
            try {
                // Try ExchangeRate-API first (free, no API key required)
                $rates = $this->fetchFromExchangeRateApi($currencies);
                if (!empty($rates)) {
                    return $rates;
                }

                // Fallback to Fixer.io if API key is available
                if (config('services.fixer.api_key')) {
                    $rates = $this->fetchFromFixer($currencies);
                    if (!empty($rates)) {
                        return $rates;
                    }
                }

                $attempts++;
                sleep(1); // Wait before retry

            } catch (Exception $e) {
                Log::warning('Currency API attempt failed', [
                    'attempt' => $attempts + 1,
                    'error' => $e->getMessage(),
                ]);
                $attempts++;
            }
        }

        // Return last known rates if all APIs fail
        return $this->getLastKnownRates($currencies);
    }

    private function fetchCryptoRates(array $currencies): array
    {
        try {
            $cryptoMap = [
                'BTC' => 'bitcoin',
                'ETH' => 'ethereum',
            ];

            $ids = array_map(fn($currency) => $cryptoMap[$currency] ?? strtolower($currency), $currencies);

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->get($this->sources['coingecko'], [
                    'ids' => implode(',', $ids),
                    'vs_currencies' => 'brl',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $rates = [];

                foreach ($currencies as $currency) {
                    $coinId = $cryptoMap[$currency] ?? strtolower($currency);
                    if (isset($data[$coinId]['brl'])) {
                        $rates[$currency] = [
                            'currency' => $currency,
                            'rate_brl' => $data[$coinId]['brl'],
                            'source' => 'coingecko',
                            'fetched_at' => now(),
                        ];
                    }
                }

                Log::info('Crypto rates fetched successfully', [
                    'currencies' => $currencies,
                    'source' => 'coingecko',
                    'count' => count($rates),
                ]);

                return $rates;
            }

        } catch (Exception $e) {
            Log::error('Failed to fetch crypto rates', [
                'error' => $e->getMessage(),
                'currencies' => $currencies,
            ]);
        }

        return $this->getLastKnownRates($currencies);
    }

    private function fetchFromExchangeRateApi(array $currencies): array
    {
        $response = Http::timeout(self::REQUEST_TIMEOUT)
            ->get($this->sources['exchangerate-api']);

        if (!$response->successful()) {
            throw new Exception('ExchangeRate-API request failed');
        }

        $data = $response->json();

        if (!isset($data['rates'])) {
            throw new Exception('Invalid response format from ExchangeRate-API');
        }

        $rates = [];
        foreach ($currencies as $currency) {
            if (isset($data['rates'][$currency])) {
                // Convert from BRL base to foreign currency rate
                $rate = 1 / $data['rates'][$currency];

                $rates[$currency] = [
                    'currency' => $currency,
                    'rate_brl' => round($rate, 6),
                    'source' => 'exchangerate-api',
                    'fetched_at' => now(),
                ];
            }
        }

        Log::info('Fiat rates fetched from ExchangeRate-API', [
            'currencies' => $currencies,
            'count' => count($rates),
        ]);

        return $rates;
    }

    private function fetchFromFixer(array $currencies): array
    {
        $apiKey = config('services.fixer.api_key');

        if (!$apiKey) {
            return [];
        }

        $response = Http::timeout(self::REQUEST_TIMEOUT)
            ->get($this->sources['fixer'], [
                'access_key' => $apiKey,
                'base' => 'EUR',
                'symbols' => implode(',', array_merge($currencies, ['BRL'])),
            ]);

        if (!$response->successful()) {
            throw new Exception('Fixer.io request failed');
        }

        $data = $response->json();

        if (!isset($data['rates'])) {
            throw new Exception('Invalid response format from Fixer.io');
        }

        $brlRate = $data['rates']['BRL'] ?? null;
        if (!$brlRate) {
            throw new Exception('BRL rate not found in Fixer.io response');
        }

        $rates = [];
        foreach ($currencies as $currency) {
            if (isset($data['rates'][$currency])) {
                // Convert EUR-based rate to BRL
                $rate = $brlRate / $data['rates'][$currency];

                $rates[$currency] = [
                    'currency' => $currency,
                    'rate_brl' => round($rate, 6),
                    'source' => 'fixer',
                    'fetched_at' => now(),
                ];
            }
        }

        Log::info('Fiat rates fetched from Fixer.io', [
            'currencies' => $currencies,
            'count' => count($rates),
        ]);

        return $rates;
    }

    private function getLastKnownRates(array $currencies): array
    {
        $rates = [];

        foreach ($currencies as $currency) {
            $lastRate = CurrencyRate::byCurrency($currency)
                ->latest()
                ->first();

            if ($lastRate) {
                $rates[$currency] = [
                    'currency' => $currency,
                    'rate_brl' => $lastRate->rate_brl,
                    'source' => $lastRate->source . '_cached',
                    'fetched_at' => $lastRate->fetched_at,
                ];
            }
        }

        Log::info('Returned last known rates', [
            'currencies' => $currencies,
            'count' => count($rates),
        ]);

        return $rates;
    }

    public function updateStoredRates(array $currencies = ['USD', 'EUR', 'BTC']): array
    {
        $rates = $this->getCurrencyRates($currencies);
        $updated = [];

        foreach ($rates as $currency => $rateData) {
            try {
                $currencyRate = CurrencyRate::updateOrCreate(
                    [
                        'currency' => $rateData['currency'],
                        'source' => $rateData['source'],
                    ],
                    [
                        'rate_brl' => $rateData['rate_brl'],
                        'fetched_at' => $rateData['fetched_at'],
                    ]
                );

                $updated[] = $currencyRate;

                Log::debug('Currency rate updated', [
                    'currency' => $currency,
                    'rate' => $rateData['rate_brl'],
                    'source' => $rateData['source'],
                ]);

            } catch (Exception $e) {
                Log::error('Failed to store currency rate', [
                    'currency' => $currency,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Clear cache after updating
        Cache::forget('currency_rates_' . implode('_', $currencies));

        Log::info('Currency rates update completed', [
            'total_requested' => count($currencies),
            'successful_updates' => count($updated),
        ]);

        return $updated;
    }

    public function getStoredRates(array $currencies = null): array
    {
        return CurrencyRate::getLatestRates($currencies);
    }

    public function isServiceHealthy(): bool
    {
        try {
            // Test with a simple USD rate fetch
            $rates = $this->getCurrencyRates(['USD']);
            return !empty($rates);
        } catch (Exception $e) {
            Log::error('Currency service health check failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}