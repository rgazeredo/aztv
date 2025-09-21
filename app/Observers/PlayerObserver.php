<?php

namespace App\Observers;

use App\Models\Player;
use App\Services\PlayerCacheService;
use App\Services\SyncCacheService;

class PlayerObserver
{
    private PlayerCacheService $playerCacheService;
    private SyncCacheService $syncCacheService;

    public function __construct(PlayerCacheService $playerCacheService, SyncCacheService $syncCacheService)
    {
        $this->playerCacheService = $playerCacheService;
        $this->syncCacheService = $syncCacheService;
    }

    /**
     * Handle the Player "created" event.
     */
    public function created(Player $player): void
    {
        // Cache the new player configuration
        $this->playerCacheService->cachePlayerConfig($player);
    }

    /**
     * Handle the Player "updated" event.
     */
    public function updated(Player $player): void
    {
        // Invalidate and refresh player cache
        $this->playerCacheService->invalidatePlayerCache($player->id);
        $this->playerCacheService->cachePlayerConfig($player);

        // Invalidate sync cache for this player
        $this->syncCacheService->invalidatePlayerSyncCache($player->id);

        // If tenant changed, invalidate old tenant cache
        if ($player->isDirty('tenant_id')) {
            $oldTenantId = $player->getOriginal('tenant_id');
            if ($oldTenantId) {
                $this->playerCacheService->invalidateTenantCache($oldTenantId);
            }
        }
    }

    /**
     * Handle the Player "deleted" event.
     */
    public function deleted(Player $player): void
    {
        // Invalidate all cache related to this player
        $this->playerCacheService->invalidatePlayerCache($player->id);
        $this->syncCacheService->invalidatePlayerSyncCache($player->id);
    }

    /**
     * Handle the Player "restored" event.
     */
    public function restored(Player $player): void
    {
        // Re-cache the restored player
        $this->playerCacheService->cachePlayerConfig($player);
    }

    /**
     * Handle the Player "force deleted" event.
     */
    public function forceDeleted(Player $player): void
    {
        // Completely remove all cache traces
        $this->playerCacheService->invalidatePlayerCache($player->id);
        $this->syncCacheService->invalidatePlayerSyncCache($player->id);
    }
}