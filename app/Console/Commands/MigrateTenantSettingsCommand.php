<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantSettingsService;
use Illuminate\Console\Command;

class MigrateTenantSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate-settings
                            {--tenant= : Specific tenant ID to migrate}
                            {--force : Force initialization even if settings already exist}
                            {--reset : Reset existing settings to defaults}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate or initialize tenant settings';

    /**
     * Execute the console command.
     */
    public function handle(TenantSettingsService $settingsService): int
    {
        $tenantId = $this->option('tenant');
        $force = $this->option('force');
        $reset = $this->option('reset');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found.");
                return Command::FAILURE;
            }
            $tenants = collect([$tenant]);
        } else {
            $tenants = Tenant::all();
        }

        $this->info("Processing settings for {$tenants->count()} tenant(s)...");

        $progressBar = $this->output->createProgressBar($tenants->count());
        $progressBar->start();

        $successful = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $existingSettings = $tenant->tenantSettings()->count();

                if ($reset) {
                    // Reset all categories to defaults
                    $categories = ['theme', 'player_defaults', 'notifications', 'system'];
                    foreach ($categories as $category) {
                        $settingsService->resetCategory($tenant, $category);
                    }
                    $this->line("  Reset settings for tenant: {$tenant->name}");
                } elseif ($existingSettings === 0 || $force) {
                    // Initialize defaults
                    $settingsService->initializeDefaults($tenant);
                    $action = $existingSettings === 0 ? 'Initialized' : 'Force-initialized';
                    $this->line("  {$action} settings for tenant: {$tenant->name}");
                } else {
                    $this->line("  Skipped tenant: {$tenant->name} (settings already exist, use --force to override)");
                }

                $successful++;
            } catch (\Exception $e) {
                $this->error("  Failed for tenant {$tenant->name}: {$e->getMessage()}");
                $failed++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Migration completed!");
        $this->info("✓ Successful: {$successful}");
        if ($failed > 0) {
            $this->error("✗ Failed: {$failed}");
        }

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}