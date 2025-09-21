<?php

namespace App\Console\Commands;

use App\Jobs\CheckAlerts;
use Illuminate\Console\Command;

class CheckAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:check
                            {--sync : Run synchronously instead of dispatching to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all alert rules and send notifications if triggered';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking alert rules...');

        if ($this->option('sync')) {
            // Run synchronously for testing
            $job = new CheckAlerts();
            $job->handle(app(\App\Services\AlertService::class));
            $this->info('Alert check completed synchronously.');
        } else {
            // Dispatch to queue
            CheckAlerts::dispatch();
            $this->info('Alert check job dispatched to queue.');
        }

        return Command::SUCCESS;
    }
}