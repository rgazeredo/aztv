<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            ($this->{'app'}['request'] ?? null)?->server?->set('HTTPS', 'on');
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Register model observers
        \App\Models\MediaFile::observe(\App\Observers\MediaFileObserver::class);
        \App\Models\Player::observe(\App\Observers\PlayerObserver::class);
        \App\Models\Playlist::observe(\App\Observers\PlaylistObserver::class);
    }
}
