<?php

namespace App\Jobs;

use App\Services\AlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('alerts');
    }

    /**
     * Execute the job.
     */
    public function handle(AlertService $alertService): void
    {
        Log::info('Starting alert check process');

        try {
            $results = $alertService->checkAllAlerts();

            if (!empty($results)) {
                Log::info('Alert check completed', [
                    'tenants_with_alerts' => count($results),
                    'total_alerts_triggered' => collect($results)
                        ->flatten(1)
                        ->where('triggered', true)
                        ->count()
                ]);
            } else {
                Log::info('Alert check completed - no alerts triggered');
            }

        } catch (\Exception $e) {
            Log::error('Failed to check alerts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CheckAlerts job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}