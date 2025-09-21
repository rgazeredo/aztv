<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Playlist;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PlayerCacheService
{
    private const CACHE_PREFIX = 'player_cache:';
    private const CONFIG_TTL = 3600; // 1 hour
    private const PLAYLIST_TTL = 900; // 15 minutes
    private const SYNC_TTL = 300; // 5 minutes

    /**
     * Cache player configuration data
     */
    public function cachePlayerConfig(Player $player): array
    {
        $cacheKey = $this->getPlayerConfigKey($player->id);

        $config = [
            'id' => $player->id,
            'name' => $player->name,
            'alias' => $player->alias,
            'location' => $player->location,
            'group' => $player->group,
            'status' => $player->status,
            'ip_address' => $player->ip_address,
            'app_version' => $player->app_version,
            'device_info' => $player->device_info,
            'settings' => $player->settings,
            'last_seen_at' => $player->last_seen_at?->toISOString(),
            'cached_at' => now()->toISOString(),
            'tenant_id' => $player->tenant_id,
        ];

        Cache::tags(['player:' . $player->id, 'tenant:' . $player->tenant_id])
            ->put($cacheKey, $config, self::CONFIG_TTL);

        return $config;
    }

    /**
     * Get cached player configuration
     */
    public function getCachedPlayerConfig(int $playerId): ?array
    {
        $cacheKey = $this->getPlayerConfigKey($playerId);
        return Cache::get($cacheKey);
    }

    /**
     * Get player configuration from cache or database
     */
    public function getPlayerConfig(int $playerId): ?array
    {
        $cached = $this->getCachedPlayerConfig($playerId);

        if ($cached) {
            return $cached;
        }

        $player = Player::find($playerId);
        if (!$player) {
            return null;
        }

        return $this->cachePlayerConfig($player);
    }

    /**
     * Cache active playlists for a player
     */
    public function cacheActivePlaylist(int $playerId, Playlist $playlist): array
    {
        $cacheKey = $this->getActivePlaylistKey($playerId);

        $playlistData = [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'description' => $playlist->description,
            'is_default' => $playlist->is_default,
            'loop_enabled' => $playlist->loop_enabled,
            'settings' => $playlist->settings,
            'tenant_id' => $playlist->tenant_id,
            'items' => $playlist->items()->with('mediaFile')->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'order' => $item->order,
                    'display_time_override' => $item->display_time_override,
                    'media_file' => [
                        'id' => $item->mediaFile->id,
                        'name' => $item->mediaFile->name,
                        'filename' => $item->mediaFile->filename,
                        'mime_type' => $item->mediaFile->mime_type,
                        'file_size' => $item->mediaFile->file_size,
                        'duration' => $item->mediaFile->duration,
                        'file_path' => $item->mediaFile->file_path,
                        'checksum' => $item->mediaFile->checksum,
                    ],
                ];
            })->toArray(),
            'cached_at' => now()->toISOString(),
        ];

        Cache::tags(['player:' . $playerId, 'playlist:' . $playlist->id, 'tenant:' . $playlist->tenant_id])
            ->put($cacheKey, $playlistData, self::PLAYLIST_TTL);

        return $playlistData;
    }

    /**
     * Get cached active playlist for player
     */
    public function getCachedActivePlaylist(int $playerId): ?array
    {
        $cacheKey = $this->getActivePlaylistKey($playerId);
        return Cache::get($cacheKey);
    }

    /**
     * Cache player sync timestamp
     */
    public function cacheSyncTimestamp(int $playerId, Carbon $timestamp): void
    {
        $cacheKey = $this->getSyncTimestampKey($playerId);

        Cache::tags(['player:' . $playerId, 'sync_data'])
            ->put($cacheKey, $timestamp->toISOString(), self::SYNC_TTL);
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
     * Cache general data with custom TTL
     */
    public function cacheData(string $key, mixed $data, int $ttl = self::CONFIG_TTL, array $tags = []): void
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        if (!empty($tags)) {
            Cache::tags($tags)->put($cacheKey, $data, $ttl);
        } else {
            Cache::put($cacheKey, $data, $ttl);
        }
    }

    /**
     * Get cached data
     */
    public function getCachedData(string $key): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        return Cache::get($cacheKey);
    }

    /**
     * Invalidate player cache
     */
    public function invalidatePlayerCache(int $playerId): void
    {
        Cache::tags(['player:' . $playerId])->flush();
    }

    /**
     * Invalidate playlist cache
     */
    public function invalidatePlaylistCache(int $playlistId): void
    {
        Cache::tags(['playlist:' . $playlistId])->flush();
    }

    /**
     * Invalidate tenant cache
     */
    public function invalidateTenantCache(int $tenantId): void
    {
        Cache::tags(['tenant:' . $tenantId])->flush();
    }

    /**
     * Invalidate all sync data cache
     */
    public function invalidateSyncCache(): void
    {
        Cache::tags(['sync_data'])->flush();
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'total_keys' => Cache::getStore()->getRedis()->dbSize(),
            'memory_usage' => Cache::getStore()->getRedis()->info()['used_memory_human'] ?? 'N/A',
            'hit_rate' => $this->calculateHitRate(),
        ];
    }

    /**
     * Warm up player cache
     */
    public function warmPlayerCache(int $playerId): array
    {
        $player = Player::with(['playlists'])->find($playerId);

        if (!$player) {
            return ['error' => 'Player not found'];
        }

        $warmed = [];

        // Cache player config
        $warmed['config'] = $this->cachePlayerConfig($player);

        // Cache active playlists
        foreach ($player->playlists as $playlist) {
            $warmed['playlists'][] = $this->cacheActivePlaylist($playerId, $playlist);
        }

        // Cache sync timestamp
        $this->cacheSyncTimestamp($playerId, $player->last_seen_at ?? now());

        return $warmed;
    }

    /**
     * Calculate cache hit rate (simplified)
     */
    private function calculateHitRate(): string
    {
        // This is a simplified implementation
        // In production, you'd want to track hits/misses more accurately
        try {
            $info = Cache::getStore()->getRedis()->info();
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 0;
            $total = $hits + $misses;

            if ($total > 0) {
                return round(($hits / $total) * 100, 2) . '%';
            }
        } catch (\Exception $e) {
            // Fallback if Redis info is not available
        }

        return 'N/A';
    }

    /**
     * Generate cache key for player config
     */
    private function getPlayerConfigKey(int $playerId): string
    {
        return self::CACHE_PREFIX . 'config:' . $playerId;
    }

    /**
     * Generate cache key for active playlist
     */
    private function getActivePlaylistKey(int $playerId): string
    {
        return self::CACHE_PREFIX . 'playlist:active:' . $playerId;
    }

    /**
     * Generate cache key for sync timestamp
     */
    private function getSyncTimestampKey(int $playerId): string
    {
        return self::CACHE_PREFIX . 'sync:timestamp:' . $playerId;
    }
}