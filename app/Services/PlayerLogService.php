<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerLog;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;

class PlayerLogService
{
    protected array $rateLimits = [
        PlayerLog::EVENT_HEARTBEAT => 60, // Max 1 per minute
        PlayerLog::EVENT_PERFORMANCE_METRIC => 300, // Max 1 per 5 minutes
        PlayerLog::EVENT_MEDIA_START => 10, // Max 1 per 10 seconds
        PlayerLog::EVENT_MEDIA_END => 10, // Max 1 per 10 seconds
    ];

    public function logMediaEvent(int $playerId, string $eventType, int $mediaFileId = null, array $eventData = []): ?PlayerLog
    {
        if (!$this->canLog($playerId, $eventType)) {
            return null;
        }

        $player = Player::find($playerId);
        if (!$player) {
            return null;
        }

        $logData = [
            'player_id' => $playerId,
            'tenant_id' => $player->tenant_id,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'media_file_id' => $mediaFileId,
            'timestamp' => now(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];

        return PlayerLog::create($logData);
    }

    public function logMediaStart(int $playerId, int $mediaFileId, array $metadata = []): ?PlayerLog
    {
        $mediaFile = MediaFile::find($mediaFileId);

        $eventData = array_merge([
            'action' => 'start',
            'media_filename' => $mediaFile?->filename,
            'media_duration' => $mediaFile?->duration,
            'started_at' => now()->toISOString(),
        ], $metadata);

        return $this->logMediaEvent(
            $playerId,
            PlayerLog::EVENT_MEDIA_START,
            $mediaFileId,
            $eventData
        );
    }

    public function logMediaEnd(int $playerId, int $mediaFileId, array $metadata = []): ?PlayerLog
    {
        $eventData = array_merge([
            'action' => 'end',
            'ended_at' => now()->toISOString(),
        ], $metadata);

        return $this->logMediaEvent(
            $playerId,
            PlayerLog::EVENT_MEDIA_END,
            $mediaFileId,
            $eventData
        );
    }

    public function logMediaError(int $playerId, int $mediaFileId = null, string $errorType = null, array $errorDetails = []): ?PlayerLog
    {
        $eventData = array_merge([
            'error_type' => $errorType,
            'error_message' => $errorDetails['message'] ?? 'Unknown media error',
            'error_code' => $errorDetails['code'] ?? null,
            'occurred_at' => now()->toISOString(),
        ], $errorDetails);

        return $this->logMediaEvent(
            $playerId,
            PlayerLog::EVENT_MEDIA_ERROR,
            $mediaFileId,
            $eventData
        );
    }

    public function logConnectivityError(int $playerId, string $errorType, array $errorDetails = []): ?PlayerLog
    {
        $player = Player::find($playerId);
        if (!$player) {
            return null;
        }

        $eventData = array_merge([
            'error_type' => $errorType,
            'error_message' => $errorDetails['message'] ?? 'Connection error',
            'network_info' => $errorDetails['network_info'] ?? null,
            'retry_count' => $errorDetails['retry_count'] ?? 0,
            'occurred_at' => now()->toISOString(),
        ], $errorDetails);

        return PlayerLog::create([
            'player_id' => $playerId,
            'tenant_id' => $player->tenant_id,
            'event_type' => PlayerLog::EVENT_CONNECTION_ERROR,
            'event_data' => $eventData,
            'timestamp' => now(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public function logPerformanceMetric(int $playerId, array $metrics): ?PlayerLog
    {
        if (!$this->canLog($playerId, PlayerLog::EVENT_PERFORMANCE_METRIC)) {
            return null;
        }

        $player = Player::find($playerId);
        if (!$player) {
            return null;
        }

        $eventData = array_merge([
            'collected_at' => now()->toISOString(),
            'cpu_usage' => $metrics['cpu_usage'] ?? null,
            'memory_usage' => $metrics['memory_usage'] ?? null,
            'memory_total' => $metrics['memory_total'] ?? null,
            'storage_usage' => $metrics['storage_usage'] ?? null,
            'storage_total' => $metrics['storage_total'] ?? null,
            'temperature' => $metrics['temperature'] ?? null,
            'network_latency' => $metrics['network_latency'] ?? null,
            'fps' => $metrics['fps'] ?? null,
            'battery_level' => $metrics['battery_level'] ?? null,
        ], $metrics);

        return PlayerLog::create([
            'player_id' => $playerId,
            'tenant_id' => $player->tenant_id,
            'event_type' => PlayerLog::EVENT_PERFORMANCE_METRIC,
            'event_data' => $eventData,
            'timestamp' => now(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public function logHeartbeat(int $playerId, array $systemInfo = []): ?PlayerLog
    {
        if (!$this->canLog($playerId, PlayerLog::EVENT_HEARTBEAT)) {
            return null;
        }

        $player = Player::find($playerId);
        if (!$player) {
            return null;
        }

        $eventData = array_merge([
            'heartbeat_at' => now()->toISOString(),
            'status' => 'alive',
            'app_version' => $systemInfo['app_version'] ?? null,
            'android_version' => $systemInfo['android_version'] ?? null,
            'device_model' => $systemInfo['device_model'] ?? null,
        ], $systemInfo);

        return PlayerLog::create([
            'player_id' => $playerId,
            'tenant_id' => $player->tenant_id,
            'event_type' => PlayerLog::EVENT_HEARTBEAT,
            'event_data' => $eventData,
            'timestamp' => now(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public function logBatch(int $playerId, array $events): array
    {
        $results = [];
        $player = Player::find($playerId);

        if (!$player) {
            return [];
        }

        foreach ($events as $event) {
            $eventType = $event['event_type'] ?? null;
            $eventData = $event['event_data'] ?? [];
            $mediaFileId = $event['media_file_id'] ?? null;
            $timestamp = isset($event['timestamp']) ? Carbon::parse($event['timestamp']) : now();

            if (!$eventType) {
                continue;
            }

            // Skip if rate limited
            if (!$this->canLog($playerId, $eventType)) {
                continue;
            }

            $logData = [
                'player_id' => $playerId,
                'tenant_id' => $player->tenant_id,
                'event_type' => $eventType,
                'event_data' => $eventData,
                'media_file_id' => $mediaFileId,
                'timestamp' => $timestamp,
                'ip_address' => $event['ip_address'] ?? Request::ip(),
                'user_agent' => $event['user_agent'] ?? Request::userAgent(),
            ];

            $results[] = PlayerLog::create($logData);
        }

        return $results;
    }

    protected function canLog(int $playerId, string $eventType): bool
    {
        if (!isset($this->rateLimits[$eventType])) {
            return true;
        }

        $cacheKey = "player_log_rate_limit:{$playerId}:{$eventType}";
        $lastLogged = Cache::get($cacheKey);

        if ($lastLogged && now()->diffInSeconds($lastLogged) < $this->rateLimits[$eventType]) {
            return false;
        }

        Cache::put($cacheKey, now(), $this->rateLimits[$eventType] + 60);
        return true;
    }

    public function getPlayerStats(int $playerId, int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $baseQuery = PlayerLog::forPlayer($playerId)
            ->where('timestamp', '>=', $startDate);

        return [
            'total_events' => (clone $baseQuery)->count(),
            'media_events' => (clone $baseQuery)->mediaEvents()->count(),
            'error_events' => (clone $baseQuery)->errorEvents()->count(),
            'performance_events' => (clone $baseQuery)->performanceEvents()->count(),
            'heartbeats' => (clone $baseQuery)->ofEventType(PlayerLog::EVENT_HEARTBEAT)->count(),
            'uptime_percentage' => $this->calculateUptimePercentage($playerId, $days),
            'avg_performance' => $this->getAveragePerformanceMetrics($playerId, $days),
            'most_played_media' => $this->getMostPlayedMedia($playerId, $days),
        ];
    }

    protected function calculateUptimePercentage(int $playerId, int $days): float
    {
        $totalMinutes = $days * 24 * 60;
        $heartbeats = PlayerLog::forPlayer($playerId)
            ->ofEventType(PlayerLog::EVENT_HEARTBEAT)
            ->where('timestamp', '>=', now()->subDays($days))
            ->count();

        // Assuming heartbeat every minute when online
        return min(100, ($heartbeats / $totalMinutes) * 100);
    }

    protected function getAveragePerformanceMetrics(int $playerId, int $days): array
    {
        $metrics = PlayerLog::forPlayer($playerId)
            ->performanceEvents()
            ->where('timestamp', '>=', now()->subDays($days))
            ->get();

        if ($metrics->isEmpty()) {
            return [];
        }

        $totalCpu = 0;
        $totalMemory = 0;
        $totalTemp = 0;
        $count = 0;

        foreach ($metrics as $metric) {
            $data = $metric->event_data ?? [];
            if (isset($data['cpu_usage'])) {
                $totalCpu += $data['cpu_usage'];
                $count++;
            }
            if (isset($data['memory_usage'])) {
                $totalMemory += $data['memory_usage'];
            }
            if (isset($data['temperature'])) {
                $totalTemp += $data['temperature'];
            }
        }

        return $count > 0 ? [
            'avg_cpu_usage' => round($totalCpu / $count, 2),
            'avg_memory_usage' => round($totalMemory / $count, 2),
            'avg_temperature' => round($totalTemp / $count, 2),
        ] : [];
    }

    protected function getMostPlayedMedia(int $playerId, int $days): array
    {
        return PlayerLog::forPlayer($playerId)
            ->ofEventType(PlayerLog::EVENT_MEDIA_START)
            ->where('timestamp', '>=', now()->subDays($days))
            ->whereNotNull('media_file_id')
            ->with('mediaFile')
            ->get()
            ->groupBy('media_file_id')
            ->map(function ($logs) {
                $mediaFile = $logs->first()->mediaFile;
                return [
                    'media_file_id' => $logs->first()->media_file_id,
                    'filename' => $mediaFile?->filename ?? 'Unknown',
                    'play_count' => $logs->count(),
                ];
            })
            ->sortByDesc('play_count')
            ->take(5)
            ->values()
            ->toArray();
    }

    public function cleanupOldLogs(int $days = 90): int
    {
        return PlayerLog::where('timestamp', '<', now()->subDays($days))->delete();
    }
}