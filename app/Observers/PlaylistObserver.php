<?php

namespace App\Observers;

use App\Models\Playlist;
use App\Services\PlayerCacheService;
use App\Services\SyncCacheService;
use App\Services\ActivityLogService;

class PlaylistObserver
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
     * Handle the Playlist "created" event.
     */
    public function created(Playlist $playlist): void
    {
        // Log the activity
        $this->activityLogService->logPlaylistCreated($playlist);

        // Invalidate tenant cache when new playlist is created
        $this->playerCacheService->invalidateTenantCache($playlist->tenant_id);
    }

    /**
     * Handle the Playlist "updated" event.
     */
    public function updated(Playlist $playlist): void
    {
        // Log the activity first
        $oldValues = $playlist->getOriginal();
        $this->activityLogService->logPlaylistModified($playlist, $oldValues);

        // Invalidate playlist cache
        $this->playerCacheService->invalidatePlaylistCache($playlist->id);

        // Invalidate cache for all players using this playlist
        $playerIds = $playlist->players()->pluck('id');
        foreach ($playerIds as $playerId) {
            $this->playerCacheService->invalidatePlayerCache($playerId);
            $this->syncCacheService->invalidatePlayerSyncCache($playerId);
        }

        // If tenant changed, invalidate old tenant cache
        if ($playlist->isDirty('tenant_id')) {
            $oldTenantId = $playlist->getOriginal('tenant_id');
            if ($oldTenantId) {
                $this->playerCacheService->invalidateTenantCache($oldTenantId);
            }
        }

        // Invalidate current tenant cache
        $this->playerCacheService->invalidateTenantCache($playlist->tenant_id);
    }

    /**
     * Handle the Playlist "deleted" event.
     */
    public function deleted(Playlist $playlist): void
    {
        // Log the activity
        $this->activityLogService->logPlaylistDeleted($playlist);

        // Invalidate playlist cache
        $this->playerCacheService->invalidatePlaylistCache($playlist->id);

        // Invalidate cache for all players that were using this playlist
        $playerIds = $playlist->players()->pluck('id');
        foreach ($playerIds as $playerId) {
            $this->playerCacheService->invalidatePlayerCache($playerId);
            $this->syncCacheService->invalidatePlayerSyncCache($playerId);
        }

        // Invalidate tenant cache
        $this->playerCacheService->invalidateTenantCache($playlist->tenant_id);
    }

    /**
     * Handle the Playlist "restored" event.
     */
    public function restored(Playlist $playlist): void
    {
        // Invalidate tenant cache when playlist is restored
        $this->playerCacheService->invalidateTenantCache($playlist->tenant_id);
    }

    /**
     * Handle the Playlist "force deleted" event.
     */
    public function forceDeleted(Playlist $playlist): void
    {
        // Same as deleted - remove all cache references
        $this->playerCacheService->invalidatePlaylistCache($playlist->id);
        $this->playerCacheService->invalidateTenantCache($playlist->tenant_id);
    }
}