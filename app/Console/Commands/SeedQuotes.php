<?php

namespace App\Console\Commands;

use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Console\Command;

class SeedQuotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quotes:seed
                            {--tenant= : Specific tenant ID to seed quotes for}
                            {--force : Force re-seeding even if quotes already exist}
                            {--category= : Seed quotes only for specific category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed default motivational quotes for tenants';

    protected QuoteService $quoteService;

    public function __construct(QuoteService $quoteService)
    {
        parent::__construct();
        $this->quoteService = $quoteService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting quote seeding process...');

        $tenantId = $this->option('tenant');
        $force = $this->option('force');
        $category = $this->option('category');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found.");
                return 1;
            }
            $tenants = collect([$tenant]);
        } else {
            $tenants = Tenant::active()->get();
        }

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');
            if ($this->confirm('Would you like to create a default tenant?')) {
                $tenant = Tenant::create([
                    'name' => 'Default',
                    'slug' => 'default',
                    'is_active' => true,
                ]);
                $tenants = collect([$tenant]);
                $this->info("Created default tenant: {$tenant->name}");
            } else {
                return 0;
            }
        }

        $totalCreated = 0;
        $totalExisting = 0;

        foreach ($tenants as $tenant) {
            $this->line("Processing tenant: {$tenant->name}");

            $existingCount = Quote::forTenant($tenant->id)->count();

            if ($existingCount > 0 && !$force) {
                $this->warn("  Tenant already has {$existingCount} quotes. Use --force to re-seed.");
                $totalExisting += $existingCount;
                continue;
            }

            if ($force && $existingCount > 0) {
                if ($this->confirm("  Delete existing {$existingCount} quotes for {$tenant->name}?")) {
                    Quote::forTenant($tenant->id)->delete();
                    $this->info("  Deleted {$existingCount} existing quotes.");
                }
            }

            $created = $this->quoteService->seedDefaultQuotes($tenant);
            $totalCreated += $created;

            $this->info("  ✓ Seeded {$created} quotes for {$tenant->name}");
        }

        // Summary
        $this->newLine();
        $this->info('Quote seeding completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Tenants Processed', $tenants->count()],
                ['New Quotes Created', $totalCreated],
                ['Existing Quotes Skipped', $totalExisting],
                ['Total Quotes in Database', Quote::count()],
            ]
        );

        // Show category breakdown if available
        if (!$category) {
            $this->newLine();
            $this->info('Quotes by Category:');
            $categories = Quote::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->orderBy('count', 'desc')
                ->get();

            $categoryData = $categories->map(function ($item) {
                $categoryName = Quote::getAvailableCategories()[$item->category] ?? $item->category;
                return [$categoryName, $item->count];
            })->toArray();

            if (!empty($categoryData)) {
                $this->table(['Category', 'Count'], $categoryData);
            }
        }

        return 0;
    }
}
