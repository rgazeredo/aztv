<?php

namespace App\Observers;

use App\Models\MediaFile;
use App\Services\PlayerCacheService;
use App\Services\SyncCacheService;
use App\Services\ActivityLogService;

class MediaFileObserver
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
     * Handle the MediaFile "created" event.
     */
    public function created(MediaFile $mediaFile): void
    {
        // Log the activity
        $this->activityLogService->logMediaUploaded($mediaFile);

        // Cache the new file checksum
        $this->syncCacheService->cacheFileChecksum($mediaFile->id, $mediaFile->checksum);

        // Invalidate tenant cache
        $this->playerCacheService->invalidateTenantCache($mediaFile->tenant_id);
    }

    /**
     * Handle the MediaFile "updated" event.
     */
    public function updated(MediaFile $mediaFile): void
    {
        // Log the activity first
        $oldValues = $mediaFile->getOriginal();
        $this->activityLogService->logMediaUpdated($mediaFile, $oldValues);

        // Invalidate file cache
        $this->syncCacheService->invalidateFileCache($mediaFile->id);

        // If checksum changed, update it in cache
        if ($mediaFile->isDirty('checksum')) {
            $this->syncCacheService->cacheFileChecksum($mediaFile->id, $mediaFile->checksum);
        }

        // Invalidate cache for all playlists using this media file
        $playlistIds = $mediaFile->playlists()->pluck('id');
        foreach ($playlistIds as $playlistId) {
            $this->playerCacheService->invalidatePlaylistCache($playlistId);

            // Invalidate cache for all players using these playlists
            $playerIds = \App\Models\Playlist::find($playlistId)->players()->pluck('id');
            foreach ($playerIds as $playerId) {
                $this->playerCacheService->invalidatePlayerCache($playerId);
                $this->syncCacheService->invalidatePlayerSyncCache($playerId);
            }
        }

        // If tenant changed, invalidate old tenant cache
        if ($mediaFile->isDirty('tenant_id')) {
            $oldTenantId = $mediaFile->getOriginal('tenant_id');
            if ($oldTenantId) {
                $this->playerCacheService->invalidateTenantCache($oldTenantId);
            }
        }

        // Invalidate current tenant cache
        $this->playerCacheService->invalidateTenantCache($mediaFile->tenant_id);
    }

    /**
     * Handle the MediaFile "deleted" event.
     */
    public function deleted(MediaFile $mediaFile): void
    {
        // Log the activity
        $this->activityLogService->logMediaDeleted($mediaFile);

        // Invalidate file cache
        $this->syncCacheService->invalidateFileCache($mediaFile->id);

        // Invalidate cache for all playlists that were using this media file
        $playlistIds = $mediaFile->playlists()->pluck('id');
        foreach ($playlistIds as $playlistId) {
            $this->playerCacheService->invalidatePlaylistCache($playlistId);

            // Invalidate cache for all players using these playlists
            $playerIds = \App\Models\Playlist::find($playlistId)->players()->pluck('id');
            foreach ($playerIds as $playerId) {
                $this->playerCacheService->invalidatePlayerCache($playerId);
                $this->syncCacheService->invalidatePlayerSyncCache($playerId);
            }
        }

        // Invalidate tenant cache
        $this->playerCacheService->invalidateTenantCache($mediaFile->tenant_id);
    }

    /**
     * Handle the MediaFile "restored" event.
     */
    public function restored(MediaFile $mediaFile): void
    {
        // Re-cache the restored file
        $this->syncCacheService->cacheFileChecksum($mediaFile->id, $mediaFile->checksum);

        // Invalidate tenant cache
        $this->playerCacheService->invalidateTenantCache($mediaFile->tenant_id);
    }

    /**
     * Handle the MediaFile "force deleted" event.
     */
    public function forceDeleted(MediaFile $mediaFile): void
    {
        // Same as deleted - remove all cache references
        $this->syncCacheService->invalidateFileCache($mediaFile->id);
        $this->playerCacheService->invalidateTenantCache($mediaFile->tenant_id);
    }
}