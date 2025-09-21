<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\UpdateCurrencyRates;
use App\Jobs\ProcessScheduledPlaylists;
use App\Jobs\CheckPlayerStatus;
use App\Jobs\RecurringPlaylistScheduler;
use App\Jobs\CheckAlerts;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule currency rates update every 5 minutes
Schedule::job(new UpdateCurrencyRates(['USD', 'EUR', 'BTC', 'ETH']))
    ->everyFiveMinutes()
    ->name('currency-rates-update')
    ->withoutOverlapping(10) // Prevent overlapping for 10 minutes
    ->onOneServer();

// Schedule currency rates update for main currencies every minute (more frequent for fiat)
Schedule::job(new UpdateCurrencyRates(['USD', 'EUR']))
    ->everyMinute()
    ->name('fiat-currency-update')
    ->withoutOverlapping(5)
    ->onOneServer();

// Schedule playlist processing every minute
Schedule::job(new ProcessScheduledPlaylists())
    ->everyMinute()
    ->name('process-scheduled-playlists')
    ->withoutOverlapping(10) // Prevent overlapping for 10 minutes
    ->onOneServer();

// Schedule player status check every 2 minutes
Schedule::job(new CheckPlayerStatus())
    ->everyTwoMinutes()
    ->name('check-player-status')
    ->withoutOverlapping(5) // Prevent overlapping for 5 minutes
    ->onOneServer();

// Schedule playlist scheduler every minute
Schedule::job(new RecurringPlaylistScheduler())
    ->everyMinute()
    ->name('recurring-playlist-scheduler')
    ->withoutOverlapping(2) // Prevent overlapping for 2 minutes
    ->onOneServer();

// Schedule alert checking every 5 minutes
Schedule::job(new CheckAlerts())
    ->everyFiveMinutes()
    ->name('check-alerts')
    ->withoutOverlapping(30) // Prevent overlapping for 30 minutes
    ->onOneServer();
