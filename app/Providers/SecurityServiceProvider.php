<?php

namespace App\Providers;

use App\Contracts\AntivirusScanner;
use App\Services\ClamAvScanner;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(AntivirusScanner::class, function ($app) {
            $scanner = config('security.antivirus.scanner', 'clamav');

            return match ($scanner) {
                'clamav' => new ClamAvScanner(),
                default => new ClamAvScanner(), // Default fallback
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}