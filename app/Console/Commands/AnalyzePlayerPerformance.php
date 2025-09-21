<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Player;
use App\Models\PlayerLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AnalyzePlayerPerformance extends Command
{
    protected $signature = 'players:analyze-performance
                           {--tenant-id= : Analyze specific tenant}
                           {--days=7 : Number of days to analyze}
                           {--export : Export results to file}';

    protected $description = 'Analyze player performance and synchronization patterns';

    public function handle()
    {
        $tenantId = $this->option('tenant-id');
        $days = (int) $this->option('days');
        $export = $this->option('export');

        $this->info("Analyzing player performance for the last {$days} days...");

        $results = [
            'overview' => $this->getOverviewStats($tenantId, $days),
            'sync_patterns' => $this->analyzeSyncPatterns($tenantId, $days),
            'query_performance' => $this->analyzeQueryPerformance($tenantId),
            'cache_efficiency' => $this->analyzeCacheEfficiency($tenantId, $days),
            'recommendations' => [],
        ];

        $this->displayResults($results);
        $this->generateRecommendations($results);

        if ($export) {
            $this->exportResults($results);
        }

        return 0;
    }

    private function getOverviewStats($tenantId, $days): array
    {
        $query = Player::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $totalPlayers = $query->count();
        $onlinePlayers = $query->online()->count();
        $offlinePlayers = $query->offline()->count();

        $activeInPeriod = $query->where('last_seen', '>=', now()->subDays($days))->count();

        return [
            'total_players' => $totalPlayers,
            'online_players' => $onlinePlayers,
            'offline_players' => $offlinePlayers,
            'active_in_period' => $activeInPeriod,
            'online_percentage' => $totalPlayers > 0 ? round(($onlinePlayers / $totalPlayers) * 100, 2) : 0,
            'active_percentage' => $totalPlayers > 0 ? round(($activeInPeriod / $totalPlayers) * 100, 2) : 0,
        ];
    }

    private function analyzeSyncPatterns($tenantId, $days): array
    {
        $query = PlayerLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->where('type', 'info')
            ->where(function ($q) {
                $q->where('message', 'like', '%sincroniza%')
                  ->orWhere('message', 'like', '%playlist%')
                  ->orWhere('message', 'like', '%sync%');
            });

        if ($tenantId) {
            $query->whereHas('player', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
        }

        $syncLogs = $query->with('player:id,name,tenant_id')->get();

        $patterns = [
            'total_sync_events' => $syncLogs->count(),
            'unique_players_syncing' => $syncLogs->pluck('player_id')->unique()->count(),
            'avg_syncs_per_player' => 0,
            'cache_hit_rate' => 0,
            'peak_sync_hours' => [],
            'sync_errors' => 0,
        ];

        if ($patterns['unique_players_syncing'] > 0) {
            $patterns['avg_syncs_per_player'] = round($patterns['total_sync_events'] / $patterns['unique_players_syncing'], 2);
        }

        // Analyze cache hit rates
        $cacheHits = $syncLogs->filter(function ($log) {
            return isset($log->data['cache_hit']) && $log->data['cache_hit'] === true;
        })->count();

        if ($patterns['total_sync_events'] > 0) {
            $patterns['cache_hit_rate'] = round(($cacheHits / $patterns['total_sync_events']) * 100, 2);
        }

        // Analyze peak hours
        $hourlySync = $syncLogs->groupBy(function ($log) {
            return $log->created_at->format('H');
        })->map(function ($logs) {
            return $logs->count();
        })->sortDesc()->take(3);

        $patterns['peak_sync_hours'] = $hourlySync->keys()->toArray();

        return $patterns;
    }

    private function analyzeQueryPerformance($tenantId): array
    {
        // Enable query logging temporarily
        DB::enableQueryLog();

        // Test different query scenarios
        $scenarios = [
            'basic_player_load' => function () use ($tenantId) {
                $query = Player::query();
                if ($tenantId) $query->where('tenant_id', $tenantId);
                return $query->take(10)->get();
            },
            'sync_data_load' => function () use ($tenantId) {
                $query = Player::withSyncData();
                if ($tenantId) $query->where('tenant_id', $tenantId);
                return $query->take(10)->get();
            },
            'active_players_with_tenant' => function () use ($tenantId) {
                return Player::activeWithTenant($tenantId)->take(10)->get();
            },
            'online_players' => function () use ($tenantId) {
                $query = Player::online();
                if ($tenantId) $query->where('tenant_id', $tenantId);
                return $query->take(10)->get();
            },
        ];

        $performance = [];

        foreach ($scenarios as $name => $scenario) {
            DB::flushQueryLog();
            $start = microtime(true);

            $scenario();

            $time = round((microtime(true) - $start) * 1000, 2);
            $queries = DB::getQueryLog();

            $performance[$name] = [
                'execution_time_ms' => $time,
                'query_count' => count($queries),
                'queries' => array_map(function ($query) {
                    return [
                        'sql' => $query['query'],
                        'time' => $query['time'],
                    ];
                }, $queries),
            ];
        }

        DB::disableQueryLog();

        return $performance;
    }

    private function analyzeCacheEfficiency($tenantId, $days): array
    {
        // Analyze cache-related logs
        $query = PlayerLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->where('type', 'info')
            ->where(function ($q) {
                $q->where('data->cache_hit', true)
                  ->orWhere('data->cache_hit', false);
            });

        if ($tenantId) {
            $query->whereHas('player', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
        }

        $cacheLogs = $query->get();

        $totalRequests = $cacheLogs->count();
        $cacheHits = $cacheLogs->where('data.cache_hit', true)->count();
        $cacheMisses = $cacheLogs->where('data.cache_hit', false)->count();

        return [
            'total_cache_requests' => $totalRequests,
            'cache_hits' => $cacheHits,
            'cache_misses' => $cacheMisses,
            'hit_rate_percentage' => $totalRequests > 0 ? round(($cacheHits / $totalRequests) * 100, 2) : 0,
            'miss_rate_percentage' => $totalRequests > 0 ? round(($cacheMisses / $totalRequests) * 100, 2) : 0,
        ];
    }

    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 PLAYER PERFORMANCE ANALYSIS REPORT');
        $this->info('====================================');

        // Overview
        $this->newLine();
        $this->info('📋 OVERVIEW:');
        $overview = $results['overview'];
        $this->line("  Total Players: {$overview['total_players']}");
        $this->line("  Online: {$overview['online_players']} ({$overview['online_percentage']}%)");
        $this->line("  Offline: {$overview['offline_players']}");
        $this->line("  Active in Period: {$overview['active_in_period']} ({$overview['active_percentage']}%)");

        // Sync Patterns
        $this->newLine();
        $this->info('🔄 SYNC PATTERNS:');
        $sync = $results['sync_patterns'];
        $this->line("  Total Sync Events: {$sync['total_sync_events']}");
        $this->line("  Unique Players Syncing: {$sync['unique_players_syncing']}");
        $this->line("  Avg Syncs per Player: {$sync['avg_syncs_per_player']}");
        $this->line("  Cache Hit Rate: {$sync['cache_hit_rate']}%");
        if (!empty($sync['peak_sync_hours'])) {
            $this->line("  Peak Sync Hours: " . implode(', ', $sync['peak_sync_hours']) . "h");
        }

        // Query Performance
        $this->newLine();
        $this->info('⚡ QUERY PERFORMANCE:');
        foreach ($results['query_performance'] as $scenario => $perf) {
            $this->line("  {$scenario}:");
            $this->line("    Time: {$perf['execution_time_ms']}ms");
            $this->line("    Queries: {$perf['query_count']}");
        }

        // Cache Efficiency
        $this->newLine();
        $this->info('💾 CACHE EFFICIENCY:');
        $cache = $results['cache_efficiency'];
        $this->line("  Total Requests: {$cache['total_cache_requests']}");
        $this->line("  Hit Rate: {$cache['hit_rate_percentage']}%");
        $this->line("  Miss Rate: {$cache['miss_rate_percentage']}%");
    }

    private function generateRecommendations(array &$results): void
    {
        $recommendations = [];

        // Analyze online percentage
        if ($results['overview']['online_percentage'] < 80) {
            $recommendations[] = "⚠️  Low online percentage ({$results['overview']['online_percentage']}%). Consider investigating connectivity issues.";
        }

        // Analyze cache hit rate
        if ($results['cache_efficiency']['hit_rate_percentage'] < 70) {
            $recommendations[] = "📈 Cache hit rate is low ({$results['cache_efficiency']['hit_rate_percentage']}%). Consider increasing cache TTL or optimizing cache keys.";
        }

        // Analyze query performance
        foreach ($results['query_performance'] as $scenario => $perf) {
            if ($perf['execution_time_ms'] > 100) {
                $recommendations[] = "🐌 Slow query performance in '{$scenario}' ({$perf['execution_time_ms']}ms). Consider adding more indexes.";
            }
            if ($perf['query_count'] > 10) {
                $recommendations[] = "🔍 High query count in '{$scenario}' ({$perf['query_count']} queries). Consider optimizing eager loading.";
            }
        }

        // Analyze sync patterns
        if ($results['sync_patterns']['cache_hit_rate'] < 60) {
            $recommendations[] = "🔄 Sync cache hit rate is low ({$results['sync_patterns']['cache_hit_rate']}%). Consider implementing better caching strategies.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "✅ Performance looks good! No major issues detected.";
        }

        $results['recommendations'] = $recommendations;

        $this->newLine();
        $this->info('💡 RECOMMENDATIONS:');
        foreach ($recommendations as $recommendation) {
            $this->line("  {$recommendation}");
        }
    }

    private function exportResults(array $results): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "player_performance_analysis_{$timestamp}.json";
        $filepath = storage_path("app/{$filename}");

        file_put_contents($filepath, json_encode($results, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("📄 Results exported to: {$filepath}");
    }
}