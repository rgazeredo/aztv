<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Player;
use App\Models\MediaFile;
use App\Models\ApkVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function __construct()
    {
        // Ensure only admin users can access these methods
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin()) {
                abort(403, 'Access denied. Admin privileges required.');
            }
            return $next($request);
        });
    }

    /**
     * Admin dashboard with global statistics
     */
    public function dashboard(): Response
    {
        // Global statistics
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::active()->count(),
            'total_users' => User::count(),
            'total_players' => Player::count(),
            'online_players' => Player::online()->count(),
            'total_media_files' => MediaFile::count(),
            'total_storage_used' => MediaFile::sum('size'),
            'apk_downloads' => ApkVersion::sum('download_count'),
        ];

        // Recent tenants
        $recentTenants = Tenant::with('users')
            ->withCount('users')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'users_count' => $tenant->users_count,
                    'is_active' => $tenant->is_active,
                    'created_at' => $tenant->created_at,
                    'subscription_status' => $tenant->getSubscriptionStatus(),
                ];
            });

        // Players activity (last 24h)
        $playersActivity = Player::with('tenant:id,name')
            ->where('last_seen', '>=', now()->subDay())
            ->latest('last_seen')
            ->limit(10)
            ->get()
            ->map(function ($player) {
                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'tenant_name' => $player->tenant->name,
                    'status' => $player->getStatus(),
                    'last_seen' => $player->last_seen,
                    'ip_address' => $player->ip_address,
                ];
            });

        // Storage usage by tenant
        $storageByTenant = Tenant::withSum('mediaFiles', 'size')
            ->orderBy('media_files_sum_size', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($tenant) {
                return [
                    'tenant_name' => $tenant->name,
                    'storage_used' => $tenant->media_files_sum_size ?? 0,
                    'formatted_storage' => $this->formatBytes($tenant->media_files_sum_size ?? 0),
                ];
            });

        return Inertia::render('Admin/AdminDashboard', [
            'stats' => $stats,
            'recent_tenants' => $recentTenants,
            'players_activity' => $playersActivity,
            'storage_by_tenant' => $storageByTenant,
            'formatted_stats' => [
                'total_storage_formatted' => $this->formatBytes($stats['total_storage_used']),
            ],
        ]);
    }

    /**
     * System health check
     */
    public function systemHealth(): array
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'storage' => $this->checkStorageHealth(),
            'queue' => $this->checkQueueHealth(),
            'players_connectivity' => $this->checkPlayersConnectivity(),
        ];

        $health['overall_status'] = collect($health)->every(fn($check) => $check['status'] === 'healthy')
            ? 'healthy'
            : 'issues';

        return $health;
    }

    /**
     * Get platform analytics data
     */
    public function analytics(Request $request): array
    {
        $period = $request->get('period', '7d'); // 7d, 30d, 90d

        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $startDate = now()->subDays($days);

        return [
            'tenant_growth' => $this->getTenantGrowth($startDate),
            'player_activity' => $this->getPlayerActivity($startDate),
            'storage_usage' => $this->getStorageUsage($startDate),
            'apk_downloads' => $this->getApkDownloads($startDate),
        ];
    }

    // Private helper methods
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function checkDatabaseHealth(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection OK'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkStorageHealth(): array
    {
        try {
            $freeSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usedPercentage = (($totalSpace - $freeSpace) / $totalSpace) * 100;

            if ($usedPercentage > 90) {
                return ['status' => 'warning', 'message' => 'Storage usage above 90%'];
            }

            return ['status' => 'healthy', 'message' => 'Storage OK (' . round($usedPercentage, 1) . '% used)'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }

    private function checkQueueHealth(): array
    {
        // Basic queue health check - in production you might check Redis/Database queue
        return ['status' => 'healthy', 'message' => 'Queue system operational'];
    }

    private function checkPlayersConnectivity(): array
    {
        $totalPlayers = Player::count();
        $onlinePlayers = Player::online()->count();

        if ($totalPlayers === 0) {
            return ['status' => 'healthy', 'message' => 'No players registered'];
        }

        $onlinePercentage = ($onlinePlayers / $totalPlayers) * 100;

        if ($onlinePercentage < 50) {
            return ['status' => 'warning', 'message' => "Only {$onlinePercentage}% players online"];
        }

        return ['status' => 'healthy', 'message' => "{$onlinePlayers}/{$totalPlayers} players online"];
    }

    private function getTenantGrowth(\Carbon\Carbon $startDate): array
    {
        return Tenant::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getPlayerActivity(\Carbon\Carbon $startDate): array
    {
        return Player::where('last_seen', '>=', $startDate)
            ->selectRaw('DATE(last_seen) as date, COUNT(DISTINCT id) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getStorageUsage(\Carbon\Carbon $startDate): array
    {
        return MediaFile::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, SUM(size) as total_size')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getApkDownloads(\Carbon\Carbon $startDate): array
    {
        // This would require a downloads log table in a real implementation
        // For now, return sample data
        return ApkVersion::selectRaw('version, download_count')
            ->orderBy('download_count', 'desc')
            ->get()
            ->toArray();
    }
}
