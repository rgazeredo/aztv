<?php

namespace App\Services;

use App\Models\Player;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SyncCacheService
{
    private const CACHE_PREFIX = 'sync_cache:';
    private const TIMESTAMP_TTL = 300; // 5 minutes
    private const CHECKSUM_TTL = 1800; // 30 minutes
    private const SYNC_DATA_TTL = 900; // 15 minutes
    private const WEATHER_TTL = 900; // 15 minutes for weather API cache
    private const QUOTES_TTL = 300; // 5 minutes for quotes API cache

    /**
     * Cache player sync timestamp
     */
    public function cacheSyncTimestamp(int $playerId, Carbon $timestamp): void
    {
        $cacheKey = $this->getSyncTimestampKey($playerId);

        Cache::tags(['player:' . $playerId, 'sync_data'])
            ->put($cacheKey, $timestamp->toISOString(), self::TIMESTAMP_TTL);
    }

    /**
     * Get cached sync timestamp
     */
    public function getCachedSyncTimestamp(int $playerId): ?Carbon
    {
        $cacheKey = $this->getSyncTimestampKey($playerId);
        $timestamp = Cache::get($cacheKey);

        return $timestamp ? Carbon::parse($timestamp) : null;
    }

    /**
     * Cache file checksum for change detection
     */
    public function cacheFileChecksum(int $fileId, string $checksum): void
    {
        $cacheKey = $this->getFileChecksumKey($fileId);

        Cache::tags(['file:' . $fileId, 'sync_data'])
            ->put($cacheKey, $checksum, self::CHECKSUM_TTL);
    }

    /**
     * Get cached file checksum
     */
    public function getCachedFileChecksum(int $fileId): ?string
    {
        $cacheKey = $this->getFileChecksumKey($fileId);
        return Cache::get($cacheKey);
    }

    /**
     * Check if file has changed based on checksum
     */
    public function hasFileChanged(MediaFile $file): bool
    {
        $cachedChecksum = $this->getCachedFileChecksum($file->id);

        if (!$cachedChecksum) {
            // No cached checksum, cache current one and consider it changed
            $this->cacheFileChecksum($file->id, $file->checksum);
            return true;
        }

        $hasChanged = $cachedChecksum !== $file->checksum;

        if ($hasChanged) {
            // Update cached checksum if it has changed
            $this->cacheFileChecksum($file->id, $file->checksum);
        }

        return $hasChanged;
    }

    /**
     * Get synchronization data from cache
     */
    public function getSyncDataFromCache(int $playerId, ?Carbon $lastSync = null): array
    {
        $cacheKey = $this->getSyncDataKey($playerId);
        $cached = Cache::get($cacheKey);

        if ($cached && $lastSync) {
            $cachedTime = Carbon::parse($cached['generated_at']);

            // Return cached data if it's newer than last sync
            if ($cachedTime->gt($lastSync)) {
                return $cached;
            }
        }

        return [];
    }

    /**
     * Cache sync data for a player
     */
    public function cacheSyncData(int $playerId, array $syncData, int $tenantId): array
    {
        $cacheKey = $this->getSyncDataKey($playerId);

        $data = [
            'player_id' => $playerId,
            'tenant_id' => $tenantId,
            'sync_data' => $syncData,
            'generated_at' => now()->toISOString(),
            'expires_at' => now()->addSeconds(self::SYNC_DATA_TTL)->toISOString(),
        ];

        Cache::tags(['player:' . $playerId, 'tenant:' . $tenantId, 'sync_data'])
            ->put($cacheKey, $data, self::SYNC_DATA_TTL);

        return $data;
    }

    /**
     * Cache weather API response
     */
    public function cacheWeatherData(string $location, array $weatherData): void
    {
        $cacheKey = $this->getWeatherKey($location);

        $data = [
            'location' => $location,
            'data' => $weatherData,
            'cached_at' => now()->toISOString(),
        ];

        Cache::tags(['weather_api', 'external_api'])
            ->put($cacheKey, $data, self::WEATHER_TTL);
    }

    /**
     * Get cached weather data
     */
    public function getCachedWeatherData(string $location): ?array
    {
        $cacheKey = $this->getWeatherKey($location);
        return Cache::get($cacheKey);
    }

    /**
     * Cache quotes API response
     */
    public function cacheQuotesData(string $category, array $quotesData): void
    {
        $cacheKey = $this->getQuotesKey($category);

        $data = [
            'category' => $category,
            'data' => $quotesData,
            'cached_at' => now()->toISOString(),
        ];

        Cache::tags(['quotes_api', 'external_api'])
            ->put($cacheKey, $data, self::QUOTES_TTL);
    }

    /**
     * Get cached quotes data
     */
    public function getCachedQuotesData(string $category): ?array
    {
        $cacheKey = $this->getQuotesKey($category);
        return Cache::get($cacheKey);
    }

    /**
     * Generate sync delta - only return changed data since last sync
     */
    public function generateSyncDelta(int $playerId, ?Carbon $lastSync = null): array
    {
        $player = Player::with(['playlists.items.mediaFile'])->find($playerId);

        if (!$player) {
            return ['error' => 'Player not found'];
        }

        $delta = [
            'player_id' => $playerId,
            'generated_at' => now()->toISOString(),
            'changes' => [],
        ];

        // Check for playlist changes
        foreach ($player->playlists as $playlist) {
            $playlistChanged = false;
            $mediaChanges = [];

            foreach ($playlist->items as $item) {
                if ($this->hasFileChanged($item->mediaFile)) {
                    $mediaChanges[] = [
                        'action' => 'update',
                        'media_file' => [
                            'id' => $item->mediaFile->id,
                            'name' => $item->mediaFile->name,
                            'filename' => $item->mediaFile->filename,
                            'file_path' => $item->mediaFile->file_path,
                            'checksum' => $item->mediaFile->checksum,
                            'updated_at' => $item->mediaFile->updated_at->toISOString(),
                        ],
                    ];
                    $playlistChanged = true;
                }
            }

            if ($playlistChanged || ($lastSync && $playlist->updated_at->gt($lastSync))) {
                $delta['changes'][] = [
                    'type' => 'playlist',
                    'action' => 'update',
                    'playlist_id' => $playlist->id,
                    'media_changes' => $mediaChanges,
                    'updated_at' => $playlist->updated_at->toISOString(),
                ];
            }
        }

        // Cache the delta
        $this->cacheSyncData($playerId, $delta, $player->tenant_id);

        return $delta;
    }

    /**
     * Invalidate sync cache for player
     */
    public function invalidatePlayerSyncCache(int $playerId): void
    {
        Cache::tags(['player:' . $playerId, 'sync_data'])->flush();
    }

    /**
     * Invalidate file checksum cache
     */
    public function invalidateFileCache(int $fileId): void
    {
        Cache::tags(['file:' . $fileId])->flush();
    }

    /**
     * Invalidate external API caches
     */
    public function invalidateExternalApiCache(): void
    {
        Cache::tags(['external_api'])->flush();
    }

    /**
     * Invalidate weather cache
     */
    public function invalidateWeatherCache(): void
    {
        Cache::tags(['weather_api'])->flush();
    }

    /**
     * Invalidate quotes cache
     */
    public function invalidateQuotesCache(): void
    {
        Cache::tags(['quotes_api'])->flush();
    }

    /**
     * Get sync cache statistics
     */
    public function getSyncCacheStats(): array
    {
        return [
            'active_sync_sessions' => $this->getActiveSyncSessions(),
            'cached_files_count' => $this->getCachedFilesCount(),
            'external_api_cache_hits' => $this->getExternalApiCacheHits(),
        ];
    }

    /**
     * Preload sync cache for player
     */
    public function preloadSyncCache(int $playerId): array
    {
        $player = Player::with(['playlists.items.mediaFile'])->find($playerId);

        if (!$player) {
            return ['error' => 'Player not found'];
        }

        $preloaded = [
            'player_id' => $playerId,
            'preloaded_at' => now()->toISOString(),
            'cached_items' => [],
        ];

        // Cache all file checksums
        foreach ($player->playlists as $playlist) {
            foreach ($playlist->items as $item) {
                $this->cacheFileChecksum($item->mediaFile->id, $item->mediaFile->checksum);
                $preloaded['cached_items'][] = [
                    'type' => 'file_checksum',
                    'file_id' => $item->mediaFile->id,
                    'filename' => $item->mediaFile->filename,
                ];
            }
        }

        // Cache sync timestamp
        $this->cacheSyncTimestamp($playerId, now());

        return $preloaded;
    }

    /**
     * Get active sync sessions count (simplified)
     */
    private function getActiveSyncSessions(): int
    {
        // This would be more sophisticated in production
        try {
            $pattern = self::CACHE_PREFIX . 'timestamp:*';
            $keys = Cache::getStore()->getRedis()->keys($pattern);
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cached files count
     */
    private function getCachedFilesCount(): int
    {
        try {
            $pattern = self::CACHE_PREFIX . 'file:checksum:*';
            $keys = Cache::getStore()->getRedis()->keys($pattern);
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get external API cache hits (simplified)
     */
    private function getExternalApiCacheHits(): array
    {
        return [
            'weather' => $this->getApiCacheCount('weather:*'),
            'quotes' => $this->getApiCacheCount('quotes:*'),
        ];
    }

    /**
     * Get API cache count by pattern
     */
    private function getApiCacheCount(string $pattern): int
    {
        try {
            $fullPattern = self::CACHE_PREFIX . $pattern;
            $keys = Cache::getStore()->getRedis()->keys($fullPattern);
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Generate cache key for sync timestamp
     */
    private function getSyncTimestampKey(int $playerId): string
    {
        return self::CACHE_PREFIX . 'timestamp:' . $playerId;
    }

    /**
     * Generate cache key for file checksum
     */
    private function getFileChecksumKey(int $fileId): string
    {
        return self::CACHE_PREFIX . 'file:checksum:' . $fileId;
    }

    /**
     * Generate cache key for sync data
     */
    private function getSyncDataKey(int $playerId): string
    {
        return self::CACHE_PREFIX . 'data:' . $playerId;
    }

    /**
     * Generate cache key for weather data
     */
    private function getWeatherKey(string $location): string
    {
        return self::CACHE_PREFIX . 'weather:' . md5($location);
    }

    /**
     * Generate cache key for quotes data
     */
    private function getQuotesKey(string $category): string
    {
        return self::CACHE_PREFIX . 'quotes:' . md5($category);
    }
}