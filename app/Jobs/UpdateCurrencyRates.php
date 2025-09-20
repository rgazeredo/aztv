<?php

namespace App\Jobs;

use App\Services\CurrencyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Exception;

class UpdateCurrencyRates implements ShouldQueue
{
    use Queueable;

    public $timeout = 120; // 2 minutes
    public $tries = 3;
    public $maxExceptions = 1;

    private array $currencies;

    /**
     * Create a new job instance.
     */
    public function __construct(array $currencies = ['USD', 'EUR', 'BTC', 'ETH'])
    {
        $this->currencies = $currencies;
        $this->onQueue('currency-updates');
    }

    /**
     * Execute the job.
     */
    public function handle(CurrencyService $currencyService): void
    {
        Log::info('Starting currency rates update', [
            'currencies' => $this->currencies,
            'job_id' => $this->job->getJobId(),
        ]);

        $startTime = microtime(true);

        try {
            // Update stored rates
            $updatedRates = $currencyService->updateStoredRates($this->currencies);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Currency rates update completed successfully', [
                'currencies_requested' => count($this->currencies),
                'rates_updated' => count($updatedRates),
                'execution_time_ms' => $executionTime,
                'updated_currencies' => array_map(fn($rate) => $rate->currency, $updatedRates),
            ]);

            // Log individual rate updates
            foreach ($updatedRates as $rate) {
                Log::debug('Currency rate updated', [
                    'currency' => $rate->currency,
                    'rate_brl' => $rate->rate_brl,
                    'source' => $rate->source,
                    'fetched_at' => $rate->fetched_at,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Currency rates update failed', [
                'currencies' => $this->currencies,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Currency rates update job failed permanently', [
            'currencies' => $this->currencies,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Could send notification to admins here
        // Notification::send($admins, new CurrencyUpdateFailed($this->currencies, $exception));
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['currency-rates', 'scheduled', 'background'];
    }
}
