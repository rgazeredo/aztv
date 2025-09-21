<?php

namespace App\Observers;

use App\Models\Player;
use App\Services\PlayerCacheService;
use App\Services\SyncCacheService;
use App\Services\ActivityLogService;

class PlayerObserver
{
    private PlayerCacheService $playerCacheService;
    private SyncCacheService $syncCacheService;
    private ActivityLogService $activityLogService;

    public function __construct(
        PlayerCacheService $playerCacheService,
        SyncCacheService $syncCacheService,
        ActivityLogService $activityLogService
    ) {
        $this->playerCacheService = $playerCacheService;
        $this->syncCacheService = $syncCacheService;
        $this->activityLogService = $activityLogService;
    }

    /**
     * Handle the Player "created" event.
     */
    public function created(Player $player): void
    {
        // Cache the new player configuration
        $this->playerCacheService->cachePlayerConfig($player);

        // Log the activity
        $this->activityLogService->logPlayerCreated($player);
    }

    /**
     * Handle the Player "updated" event.
     */
    public function updated(Player $player): void
    {
        // Log the activity first (before cache invalidation changes the original values)
        $oldValues = $player->getOriginal();
        $this->activityLogService->logPlayerUpdated($player, $oldValues);

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
        // Log the activity
        $this->activityLogService->logPlayerDeleted($player);

        // Invalidate all cache related to this player
        $this->playerCacheService->invalidatePlayerCache($player->id);
        $this->syncCacheService->invalidatePlayerSyncCache($player->id);
    }

    /**
     * Handle the Player "restored" event.
     */
    public function restored(Player $player): void
    {
        // Log the activity
        $this->activityLogService->log(
            'restored',
            $player,
            null,
            $player->toArray(),
            "Player '{$player->name}' foi restaurado"
        );

        // Re-cache the restored player
        $this->playerCacheService->cachePlayerConfig($player);
    }

    /**
     * Handle the Player "force deleted" event.
     */
    public function forceDeleted(Player $player): void
    {
        // Log the activity
        $this->activityLogService->log(
            'force_deleted',
            $player,
            $player->toArray(),
            null,
            "Player '{$player->name}' foi excluído permanentemente"
        );

        // Completely remove all cache traces
        $this->playerCacheService->invalidatePlayerCache($player->id);
        $this->syncCacheService->invalidatePlayerSyncCache($player->id);
    }
}