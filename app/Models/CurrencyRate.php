<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency',
        'rate_brl',
        'source',
        'fetched_at',
    ];

    protected $casts = [
        'rate_brl' => 'decimal:6',
        'fetched_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('updated_at', '>=', now()->subMinutes(30));
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', strtoupper($currency));
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('updated_at', 'desc');
    }

    public function isStale(int $minutesThreshold = 10): bool
    {
        return $this->updated_at->lt(now()->subMinutes($minutesThreshold));
    }

    public function getFormattedRateAttribute(): string
    {
        return 'R$ ' . number_format($this->rate_brl, 2, ',', '.');
    }

    public function getAgeInMinutesAttribute(): int
    {
        return $this->updated_at->diffInMinutes(now());
    }

    public static function getAvailableCurrencies(): array
    {
        return [
            'USD' => 'Dólar Americano',
            'EUR' => 'Euro',
            'GBP' => 'Libra Esterlina',
            'BTC' => 'Bitcoin',
            'ETH' => 'Ethereum',
            'ARS' => 'Peso Argentino',
            'JPY' => 'Iene Japonês',
            'CAD' => 'Dólar Canadense',
        ];
    }

    public static function getLatestRates(array $currencies = null): array
    {
        $query = static::latest();

        if ($currencies) {
            $currencies = array_map('strtoupper', $currencies);
            $query->whereIn('currency', $currencies);
        }

        return $query->get()
            ->groupBy('currency')
            ->map(function ($rates) {
                return $rates->first();
            })
            ->toArray();
    }
}
