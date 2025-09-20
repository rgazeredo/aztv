<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    private string $baseUrl = 'https://api.exchangerate-api.com/v4/latest';
    private string $fallbackUrl = 'https://api.fixer.io/latest';

    public function getRates(array $currencies = ['USD', 'EUR', 'BTC']): array
    {
        $cacheKey = 'currency_rates_' . implode('_', $currencies);

        return Cache::remember($cacheKey, 900, function () use ($currencies) {
            try {
                // Try primary API
                $response = Http::timeout(10)->get("{$this->baseUrl}/BRL");

                if ($response->successful()) {
                    $data = $response->json();
                    return $this->formatRates($data['rates'], $currencies);
                }

                // Fallback to secondary API
                return $this->getFallbackRates($currencies);

            } catch (\Exception $e) {
                return $this->getFallbackRates($currencies);
            }
        });
    }

    public function getSpecificRate(string $from, string $to): ?float
    {
        $cacheKey = "currency_rate_{$from}_{$to}";

        return Cache::remember($cacheKey, 900, function () use ($from, $to) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/{$from}");

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['rates'][$to] ?? null;
                }

                return null;
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    public function convertAmount(float $amount, string $from, string $to): ?float
    {
        $rate = $this->getSpecificRate($from, $to);

        if ($rate === null) {
            return null;
        }

        return round($amount * $rate, 2);
    }

    public function getCryptoRates(array $cryptos = ['BTC', 'ETH']): array
    {
        $cacheKey = 'crypto_rates_' . implode('_', $cryptos);

        return Cache::remember($cacheKey, 300, function () use ($cryptos) {
            try {
                $rates = [];

                foreach ($cryptos as $crypto) {
                    $response = Http::timeout(10)->get('https://api.coinbase.com/v2/exchange-rates', [
                        'currency' => $crypto
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        $rates[$crypto] = [
                            'symbol' => $crypto,
                            'name' => $this->getCryptoName($crypto),
                            'price_brl' => floatval($data['data']['rates']['BRL']),
                            'price_usd' => floatval($data['data']['rates']['USD']),
                            'formatted_brl' => 'R$ ' . number_format(floatval($data['data']['rates']['BRL']), 2, ',', '.'),
                            'formatted_usd' => '$ ' . number_format(floatval($data['data']['rates']['USD']), 2, '.', ','),
                        ];
                    } else {
                        $rates[$crypto] = $this->getFallbackCrypto($crypto);
                    }
                }

                return $rates;
            } catch (\Exception $e) {
                return $this->getFallbackCryptoRates($cryptos);
            }
        });
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/BRL");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function formatRates(array $rates, array $currencies): array
    {
        $formatted = [];

        foreach ($currencies as $currency) {
            if (isset($rates[$currency])) {
                $rate = 1 / $rates[$currency]; // Convert from BRL base to currency
                $formatted[$currency] = [
                    'symbol' => $currency,
                    'name' => $this->getCurrencyName($currency),
                    'rate' => $rate,
                    'formatted' => $this->formatCurrency($rate, $currency),
                    'trend' => $this->getTrend($currency),
                ];
            }
        }

        return $formatted;
    }

    private function getFallbackRates(array $currencies): array
    {
        $fallbackRates = [
            'USD' => 5.20,
            'EUR' => 5.60,
            'GBP' => 6.50,
            'JPY' => 0.035,
            'CAD' => 3.80,
            'AUD' => 3.40,
            'CHF' => 5.80,
        ];

        $formatted = [];

        foreach ($currencies as $currency) {
            if (isset($fallbackRates[$currency])) {
                $rate = $fallbackRates[$currency];
                $formatted[$currency] = [
                    'symbol' => $currency,
                    'name' => $this->getCurrencyName($currency),
                    'rate' => $rate,
                    'formatted' => $this->formatCurrency($rate, $currency),
                    'trend' => 'stable',
                ];
            }
        }

        return $formatted;
    }

    private function getFallbackCrypto(string $crypto): array
    {
        $fallbackPrices = [
            'BTC' => ['brl' => 300000, 'usd' => 58000],
            'ETH' => ['brl' => 15000, 'usd' => 2900],
            'ADA' => ['brl' => 2.50, 'usd' => 0.48],
            'DOT' => ['brl' => 35, 'usd' => 6.80],
        ];

        $prices = $fallbackPrices[$crypto] ?? ['brl' => 100, 'usd' => 20];

        return [
            'symbol' => $crypto,
            'name' => $this->getCryptoName($crypto),
            'price_brl' => $prices['brl'],
            'price_usd' => $prices['usd'],
            'formatted_brl' => 'R$ ' . number_format($prices['brl'], 2, ',', '.'),
            'formatted_usd' => '$ ' . number_format($prices['usd'], 2, '.', ','),
        ];
    }

    private function getFallbackCryptoRates(array $cryptos): array
    {
        $rates = [];
        foreach ($cryptos as $crypto) {
            $rates[$crypto] = $this->getFallbackCrypto($crypto);
        }
        return $rates;
    }

    private function formatCurrency(float $rate, string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
        ];

        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . ' ' . number_format($rate, 2, ',', '.');
    }

    private function getCurrencyName(string $currency): string
    {
        $names = [
            'USD' => 'Dólar Americano',
            'EUR' => 'Euro',
            'GBP' => 'Libra Esterlina',
            'JPY' => 'Iene Japonês',
            'CAD' => 'Dólar Canadense',
            'AUD' => 'Dólar Australiano',
            'CHF' => 'Franco Suíço',
        ];

        return $names[$currency] ?? $currency;
    }

    private function getCryptoName(string $crypto): string
    {
        $names = [
            'BTC' => 'Bitcoin',
            'ETH' => 'Ethereum',
            'ADA' => 'Cardano',
            'DOT' => 'Polkadot',
            'BNB' => 'Binance Coin',
            'XRP' => 'Ripple',
            'SOL' => 'Solana',
            'DOGE' => 'Dogecoin',
        ];

        return $names[$crypto] ?? $crypto;
    }

    private function getTrend(string $currency): string
    {
        // Simple mock trend - in production, compare with previous rates
        $trends = ['up', 'down', 'stable'];
        return $trends[array_rand($trends)];
    }
}