<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\MediaFile;
use App\Models\Playlist;
use App\Models\ApkVersion;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'tenant']);
    }

    public function index(Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        // Cache metrics for 5 minutes
        $cacheKey = "dashboard_metrics_tenant_{$tenant->id}";
        $metrics = Cache::remember($cacheKey, 300, function () use ($tenant) {
            return $this->calculateMetrics($tenant);
        });

        // Get recent data (not cached for freshness)
        $recentData = $this->getRecentData($tenant);

        return Inertia::render('dashboard', [
            'metrics' => $metrics,
            'recentData' => $recentData,
            'tenant' => $tenant->only(['id', 'name', 'slug']),
        ]);
    }

    public function getMetrics(Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        // Always return fresh metrics for AJAX calls
        $metrics = $this->calculateMetrics($tenant);

        // Update cache
        $cacheKey = "dashboard_metrics_tenant_{$tenant->id}";
        Cache::put($cacheKey, $metrics, 300);

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'last_updated' => now()->toISOString(),
        ]);
    }

    private function calculateMetrics(Tenant $tenant): array
    {
        $now = now();
        $dayAgo = $now->copy()->subDay();

        // Player statistics
        $totalPlayers = Player::where('tenant_id', $tenant->id)->count();
        $onlinePlayers = Player::where('tenant_id', $tenant->id)
            ->where('last_seen', '>=', $dayAgo)
            ->count();
        $offlinePlayers = $totalPlayers - $onlinePlayers;

        // Storage statistics
        $storageUsed = MediaFile::where('tenant_id', $tenant->id)
            ->sum('size');

        // Get storage limit from tenant's plan (in bytes)
        $storageLimit = $tenant->plan ? ($tenant->plan->storage_limit * 1024 * 1024 * 1024) : 0; // Convert GB to bytes
        $storagePercentage = $storageLimit > 0 ? round(($storageUsed / $storageLimit) * 100, 2) : 0;

        // Content statistics
        $totalMedia = MediaFile::where('tenant_id', $tenant->id)->count();
        $totalPlaylists = Playlist::where('tenant_id', $tenant->id)->count();

        // Recent activity statistics
        $mediaThisWeek = MediaFile::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $now->copy()->subWeek())
            ->count();

        $playlistsThisWeek = Playlist::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $now->copy()->subWeek())
            ->count();

        // APK download statistics (if available)
        $apkDownloads = $this->getApkDownloadStats($tenant);

        return [
            'players' => [
                'total' => $totalPlayers,
                'online' => $onlinePlayers,
                'offline' => $offlinePlayers,
                'online_percentage' => $totalPlayers > 0 ? round(($onlinePlayers / $totalPlayers) * 100, 1) : 0,
            ],
            'storage' => [
                'used' => $storageUsed,
                'used_formatted' => $this->formatBytes($storageUsed),
                'limit' => $storageLimit,
                'limit_formatted' => $this->formatBytes($storageLimit),
                'percentage' => $storagePercentage,
                'available' => max(0, $storageLimit - $storageUsed),
                'available_formatted' => $this->formatBytes(max(0, $storageLimit - $storageUsed)),
            ],
            'content' => [
                'total_media' => $totalMedia,
                'total_playlists' => $totalPlaylists,
                'media_this_week' => $mediaThisWeek,
                'playlists_this_week' => $playlistsThisWeek,
            ],
            'activity' => [
                'new_media_this_week' => $mediaThisWeek,
                'new_playlists_this_week' => $playlistsThisWeek,
                'active_players_today' => $onlinePlayers,
            ],
            'apk_downloads' => $apkDownloads,
        ];
    }

    private function getRecentData(Tenant $tenant): array
    {
        // Latest 5 media files with thumbnails
        $recentMedia = MediaFile::where('tenant_id', $tenant->id)
            ->select(['id', 'filename', 'original_name', 'mime_type', 'size', 'thumbnail_path', 'created_at'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->original_name,
                    'filename' => $media->filename,
                    'mime_type' => $media->mime_type,
                    'size' => $this->formatBytes($media->size),
                    'thumbnail_url' => $media->thumbnail_path ? asset('storage/' . $media->thumbnail_path) : null,
                    'created_at' => $media->created_at->format('d/m/Y H:i'),
                    'created_at_human' => $media->created_at->diffForHumans(),
                ];
            });

        // Latest 3 playlists
        $recentPlaylists = Playlist::where('tenant_id', $tenant->id)
            ->select(['id', 'name', 'description', 'created_at', 'updated_at'])
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(function ($playlist) {
                return [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'description' => $playlist->description,
                    'items_count' => $playlist->getItemsCount(),
                    'updated_at' => $playlist->updated_at->format('d/m/Y H:i'),
                    'updated_at_human' => $playlist->updated_at->diffForHumans(),
                ];
            });

        // Recent player activity
        $recentPlayerActivity = Player::where('tenant_id', $tenant->id)
            ->select(['id', 'name', 'alias', 'status', 'last_seen', 'ip_address'])
            ->orderBy('last_seen', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($player) {
                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'alias' => $player->alias,
                    'status' => $player->status,
                    'is_online' => $player->last_seen && $player->last_seen->diffInHours(now()) < 24,
                    'last_seen' => $player->last_seen ? $player->last_seen->format('d/m/Y H:i') : 'Nunca',
                    'last_seen_human' => $player->last_seen ? $player->last_seen->diffForHumans() : 'Nunca conectado',
                    'ip_address' => $player->ip_address,
                ];
            });

        return [
            'recent_media' => $recentMedia,
            'recent_playlists' => $recentPlaylists,
            'recent_player_activity' => $recentPlayerActivity,
        ];
    }

    private function getApkDownloadStats(Tenant $tenant): array
    {
        // Get APK download statistics for this tenant
        // This would require tracking downloads in a separate table or log
        // For now, return basic APK info

        $activeApk = ApkVersion::where('is_active', true)->first();

        return [
            'active_apk_version' => $activeApk?->version ?? 'N/A',
            'active_apk_size' => $activeApk ? $this->formatBytes($activeApk->file_size ?? 0) : 'N/A',
            'last_apk_update' => $activeApk?->created_at?->format('d/m/Y') ?? 'N/A',
        ];
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = log($bytes, 1024);
        $index = floor($base);

        return round(pow(1024, $base - $index), $precision) . ' ' . $units[$index];
    }

    private function getCurrentTenant(Request $request): Tenant
    {
        return $request->user()->tenant ?? Tenant::first();
    }
}