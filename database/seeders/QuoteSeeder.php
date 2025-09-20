<?php

namespace Database\Seeders;

use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $quoteService = app(QuoteService::class);
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->info('No tenants found. Creating default tenant for quotes...');
            $tenant = Tenant::create([
                'name' => 'Default',
                'slug' => 'default',
                'is_active' => true,
            ]);
            $tenants = collect([$tenant]);
        }

        foreach ($tenants as $tenant) {
            $created = $quoteService->seedDefaultQuotes($tenant);
            $this->command->info("Seeded {$created} quotes for tenant: {$tenant->name}");
        }

        $totalQuotes = Quote::count();
        $this->command->info("Total quotes in database: {$totalQuotes}");
    }
}
