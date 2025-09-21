<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\TenantSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DefaultTenantSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settingsService = app(TenantSettingsService::class);
        $tenants = Tenant::all();

        $this->command->info("Initializing default settings for {$tenants->count()} tenants...");

        foreach ($tenants as $tenant) {
            try {
                $settingsService->initializeDefaults($tenant);
                $this->command->info("✓ Default settings initialized for tenant: {$tenant->name} (ID: {$tenant->id})");
            } catch (\Exception $e) {
                $this->command->error("✗ Failed to initialize settings for tenant {$tenant->id}: {$e->getMessage()}");
                Log::error("Failed to initialize default settings for tenant", [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->command->info("Default tenant settings seeding completed!");
    }
}