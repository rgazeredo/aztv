<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\Tenant;
use App\Services\PlayerCacheService;
use App\Services\SyncCacheService;
use Illuminate\Console\Command;

class CacheWarmPlayers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:warm-players
                          {--tenant= : Warm cache for specific tenant ID}
                          {--player= : Warm cache for specific player ID}
                          {--force : Force refresh existing cache}';

    /**
     * The console command description.
     */
    protected $description = 'Warm up player cache with configuration and playlist data';

    private PlayerCacheService $playerCacheService;
    private SyncCacheService $syncCacheService;

    public function __construct(PlayerCacheService $playerCacheService, SyncCacheService $syncCacheService)
    {
        parent::__construct();
        $this->playerCacheService = $playerCacheService;
        $this->syncCacheService = $syncCacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔥 Starting cache warming for players...');

        $tenantId = $this->option('tenant');
        $playerId = $this->option('player');
        $force = $this->option('force');

        if ($playerId) {
            return $this->warmSinglePlayer($playerId, $force);
        }

        if ($tenantId) {
            return $this->warmTenantPlayers($tenantId, $force);
        }

        return $this->warmAllPlayers($force);
    }

    /**
     * Warm cache for a single player
     */
    private function warmSinglePlayer(int $playerId, bool $force): int
    {
        $player = Player::find($playerId);

        if (!$player) {
            $this->error("❌ Player {$playerId} not found");
            return Command::FAILURE;
        }

        $this->info("🎯 Warming cache for player: {$player->name} (ID: {$playerId})");

        if ($force) {
            $this->playerCacheService->invalidatePlayerCache($playerId);
            $this->syncCacheService->invalidatePlayerSyncCache($playerId);
        }

        $result = $this->warmPlayerCache($player);

        if (isset($result['error'])) {
            $this->error("❌ Failed to warm cache: {$result['error']}");
            return Command::FAILURE;
        }

        $this->displayWarmingResults($player, $result);
        $this->info('✅ Single player cache warming completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Warm cache for all players in a tenant
     */
    private function warmTenantPlayers(int $tenantId, bool $force): int
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("❌ Tenant {$tenantId} not found");
            return Command::FAILURE;
        }

        $this->info("🏢 Warming cache for tenant: {$tenant->name} (ID: {$tenantId})");

        if ($force) {
            $this->playerCacheService->invalidateTenantCache($tenantId);
        }

        $players = Player::where('tenant_id', $tenantId)->get();

        if ($players->isEmpty()) {
            $this->warn("⚠️ No players found for tenant {$tenant->name}");
            return Command::SUCCESS;
        }

        $this->info("📋 Found {$players->count()} players to warm");

        $bar = $this->output->createProgressBar($players->count());
        $bar->start();

        $warmed = 0;
        $failed = 0;

        foreach ($players as $player) {
            if ($force) {
                $this->playerCacheService->invalidatePlayerCache($player->id);
                $this->syncCacheService->invalidatePlayerSyncCache($player->id);
            }

            $result = $this->warmPlayerCache($player);

            if (isset($result['error'])) {
                $failed++;
            } else {
                $warmed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("✅ Tenant cache warming completed: {$warmed} success, {$failed} failed");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Warm cache for all players
     */
    private function warmAllPlayers(bool $force): int
    {
        $this->info("🌐 Warming cache for all players...");

        if ($force) {
            $this->info("🧹 Force refresh enabled - clearing existing cache...");
            $this->playerCacheService->invalidateSyncCache();
        }

        $players = Player::with(['tenant'])->get();

        if ($players->isEmpty()) {
            $this->warn("⚠️ No players found in the system");
            return Command::SUCCESS;
        }

        $this->info("📋 Found {$players->count()} players to warm");

        $bar = $this->output->createProgressBar($players->count());
        $bar->start();

        $warmed = 0;
        $failed = 0;
        $byTenant = [];

        foreach ($players as $player) {
            if ($force) {
                $this->playerCacheService->invalidatePlayerCache($player->id);
                $this->syncCacheService->invalidatePlayerSyncCache($player->id);
            }

            $result = $this->warmPlayerCache($player);

            if (isset($result['error'])) {
                $failed++;
            } else {
                $warmed++;
                $tenantName = $player->tenant->name ?? 'Unknown';
                $byTenant[$tenantName] = ($byTenant[$tenantName] ?? 0) + 1;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("✅ Global cache warming completed: {$warmed} success, {$failed} failed");

        if (!empty($byTenant)) {
            $this->info("📊 Players warmed by tenant:");
            foreach ($byTenant as $tenant => $count) {
                $this->line("   • {$tenant}: {$count} players");
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Warm cache for a specific player
     */
    private function warmPlayerCache(Player $player): array
    {
        try {
            // Warm player cache
            $playerResult = $this->playerCacheService->warmPlayerCache($player->id);

            // Warm sync cache
            $syncResult = $this->syncCacheService->preloadSyncCache($player->id);

            return [
                'player' => $playerResult,
                'sync' => $syncResult,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Display warming results for a player
     */
    private function displayWarmingResults(Player $player, array $result): void
    {
        $this->line("   📋 Player: {$player->name}");
        $this->line("   🏢 Tenant: {$player->tenant->name}");

        if (isset($result['player']['config'])) {
            $this->line("   ⚙️ Config cached: ✅");
        }

        if (isset($result['player']['playlists'])) {
            $playlistCount = count($result['player']['playlists']);
            $this->line("   🎵 Playlists cached: {$playlistCount}");
        }

        if (isset($result['sync']['cached_items'])) {
            $itemCount = count($result['sync']['cached_items']);
            $this->line("   🔄 Sync items cached: {$itemCount}");
        }
    }
}