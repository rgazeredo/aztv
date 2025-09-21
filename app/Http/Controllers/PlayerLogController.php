<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\PlayerLog;
use App\Services\PlayerLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class PlayerLogController extends Controller
{
    protected PlayerLogService $playerLogService;

    public function __construct(PlayerLogService $playerLogService)
    {
        $this->playerLogService = $playerLogService;
    }

    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $query = PlayerLog::forTenant($tenantId)
            ->with(['player', 'mediaFile']);

        // Filtros
        if ($playerId = $request->get('player_id')) {
            $query->forPlayer($playerId);
        }

        if ($eventType = $request->get('event_type')) {
            $query->ofEventType($eventType);
        }

        if ($mediaFileId = $request->get('media_file_id')) {
            $query->forMediaFile($mediaFileId);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('player', function ($playerQuery) use ($search) {
                    $playerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('alias', 'like', "%{$search}%");
                })
                ->orWhereHas('mediaFile', function ($mediaQuery) use ($search) {
                    $mediaQuery->where('filename', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%");
                });
            });
        }

        // Filtro por período
        if ($startDate = $request->get('start_date')) {
            $query->where('timestamp', '>=', $startDate . ' 00:00:00');
        }

        if ($endDate = $request->get('end_date')) {
            $query->where('timestamp', '<=', $endDate . ' 23:59:59');
        }

        // Período padrão: últimas 24 horas se nenhum filtro de data for especificado
        if (!$startDate && !$endDate) {
            $query->recent(24);
        }

        // Filtro por tipo de evento (grupos)
        if ($eventGroup = $request->get('event_group')) {
            switch ($eventGroup) {
                case 'media':
                    $query->mediaEvents();
                    break;
                case 'performance':
                    $query->performanceEvents();
                    break;
                case 'errors':
                    $query->errorEvents();
                    break;
            }
        }

        $logs = $query->orderBy('timestamp', 'desc')
            ->paginate($request->get('per_page', 25))
            ->withQueryString();

        // Transformar dados para o frontend
        $logsData = $logs->getCollection()->map(function ($log) {
            return [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'event_type_name' => $log->getEventTypeName(),
                'event_data' => $log->event_data,
                'timestamp' => $log->timestamp,
                'is_error' => $log->isErrorEvent(),
                'is_media_event' => $log->isMediaEvent(),
                'player' => $log->player ? [
                    'id' => $log->player->id,
                    'name' => $log->player->name,
                    'alias' => $log->player->alias,
                ] : null,
                'media_file' => $log->mediaFile ? [
                    'id' => $log->mediaFile->id,
                    'filename' => $log->mediaFile->filename,
                    'original_name' => $log->mediaFile->original_name,
                ] : null,
            ];
        });

        // Obter dados para filtros
        $players = Player::forTenant($tenantId)
            ->select('id', 'name', 'alias')
            ->orderBy('name')
            ->get();

        $eventTypes = PlayerLog::forTenant($tenantId)
            ->distinct()
            ->pluck('event_type')
            ->filter()
            ->map(function ($type) {
                return [
                    'value' => $type,
                    'label' => PlayerLog::where('event_type', $type)->first()?->getEventTypeName() ?? $type,
                ];
            })
            ->sortBy('label')
            ->values();

        return Inertia::render('PlayerLogs/Index', [
            'logs' => [
                'data' => $logsData,
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => $request->only([
                'player_id', 'event_type', 'media_file_id', 'event_group',
                'search', 'start_date', 'end_date', 'per_page'
            ]),
            'players' => $players,
            'event_types' => $eventTypes,
            'stats' => $this->getStats($tenantId),
        ]);
    }

    public function show(PlayerLog $playerLog): Response
    {
        $this->authorize('view', $playerLog);

        $playerLog->load(['player', 'mediaFile']);

        // Buscar logs relacionados do mesmo player nos últimos 5 minutos
        $relatedLogs = PlayerLog::forPlayer($playerLog->player_id)
            ->where('id', '!=', $playerLog->id)
            ->where('timestamp', '>=', $playerLog->timestamp->subMinutes(5))
            ->where('timestamp', '<=', $playerLog->timestamp->addMinutes(5))
            ->with(['mediaFile'])
            ->orderBy('timestamp', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'event_type_name' => $log->getEventTypeName(),
                    'timestamp' => $log->timestamp,
                    'event_data' => $log->event_data,
                ];
            });

        return Inertia::render('PlayerLogs/Show', [
            'log' => [
                'id' => $playerLog->id,
                'event_type' => $playerLog->event_type,
                'event_type_name' => $playerLog->getEventTypeName(),
                'event_data' => $playerLog->event_data,
                'timestamp' => $playerLog->timestamp,
                'ip_address' => $playerLog->ip_address,
                'user_agent' => $playerLog->user_agent,
                'is_error' => $playerLog->isErrorEvent(),
                'is_media_event' => $playerLog->isMediaEvent(),
                'player' => $playerLog->player ? [
                    'id' => $playerLog->player->id,
                    'name' => $playerLog->player->name,
                    'alias' => $playerLog->player->alias,
                    'location' => $playerLog->player->location,
                ] : null,
                'media_file' => $playerLog->mediaFile ? [
                    'id' => $playerLog->mediaFile->id,
                    'filename' => $playerLog->mediaFile->filename,
                    'original_name' => $playerLog->mediaFile->original_name,
                    'duration' => $playerLog->mediaFile->duration,
                ] : null,
            ],
            'related_logs' => $relatedLogs,
        ]);
    }

    public function export(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $query = PlayerLog::forTenant($tenantId)
            ->with(['player', 'mediaFile']);

        // Aplicar os mesmos filtros da interface
        if ($playerId = $request->get('player_id')) {
            $query->forPlayer($playerId);
        }

        if ($eventType = $request->get('event_type')) {
            $query->ofEventType($eventType);
        }

        if ($startDate = $request->get('start_date')) {
            $query->where('timestamp', '>=', $startDate . ' 00:00:00');
        }

        if ($endDate = $request->get('end_date')) {
            $query->where('timestamp', '<=', $endDate . ' 23:59:59');
        }

        $logs = $query->orderBy('timestamp', 'desc')
            ->limit(10000) // Limite para evitar problemas de memória
            ->get();

        $format = $request->get('format', 'csv');

        if ($format === 'json') {
            return response()->json([
                'data' => $logs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'timestamp' => $log->timestamp->toISOString(),
                        'event_type' => $log->event_type,
                        'event_data' => $log->event_data,
                        'player_name' => $log->player?->name,
                        'media_filename' => $log->mediaFile?->filename,
                        'ip_address' => $log->ip_address,
                    ];
                }),
                'exported_at' => now()->toISOString(),
                'total_records' => $logs->count(),
            ]);
        }

        // CSV Export
        $filename = 'player_logs_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, [
                'ID',
                'Timestamp',
                'Event Type',
                'Player Name',
                'Media File',
                'IP Address',
                'Event Data'
            ]);

            // Data rows
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->timestamp->toISOString(),
                    $log->getEventTypeName(),
                    $log->player?->name ?? 'N/A',
                    $log->mediaFile?->filename ?? 'N/A',
                    $log->ip_address ?? 'N/A',
                    json_encode($log->event_data),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function dashboard(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $playerId = $request->get('player_id');
        $days = $request->get('days', 7);

        if ($playerId) {
            $stats = $this->playerLogService->getPlayerStats($playerId, $days);
            $player = Player::forTenant($tenantId)->find($playerId);

            return response()->json([
                'player' => $player ? [
                    'id' => $player->id,
                    'name' => $player->name,
                    'alias' => $player->alias,
                ] : null,
                'stats' => $stats,
                'charts' => $this->getPlayerChartData($playerId, $days),
            ]);
        }

        // Dashboard geral do tenant
        return response()->json([
            'overview' => $this->getTenantOverview($tenantId, $days),
            'top_players' => $this->getTopPlayers($tenantId, $days),
            'charts' => $this->getTenantChartData($tenantId, $days),
        ]);
    }

    protected function getStats(int $tenantId): array
    {
        $recentLogs = PlayerLog::forTenant($tenantId)->recent(24);

        return [
            'total_logs_24h' => $recentLogs->count(),
            'error_logs_24h' => (clone $recentLogs)->errorEvents()->count(),
            'media_events_24h' => (clone $recentLogs)->mediaEvents()->count(),
            'active_players_24h' => $recentLogs->distinct('player_id')->count('player_id'),
            'performance_score' => $this->calculatePerformanceScore($tenantId),
        ];
    }

    protected function calculatePerformanceScore(int $tenantId): string
    {
        $totalEvents = PlayerLog::forTenant($tenantId)->recent(24)->count();
        $errorEvents = PlayerLog::forTenant($tenantId)->recent(24)->errorEvents()->count();

        if ($totalEvents === 0) {
            return 'unknown';
        }

        $errorRate = ($errorEvents / $totalEvents) * 100;

        if ($errorRate < 1) return 'excellent';
        if ($errorRate < 5) return 'good';
        if ($errorRate < 15) return 'fair';
        return 'poor';
    }

    protected function getPlayerChartData(int $playerId, int $days): array
    {
        $startDate = now()->subDays($days);

        // Events por hora nas últimas 24h
        $hourlyEvents = PlayerLog::forPlayer($playerId)
            ->where('timestamp', '>=', now()->subHours(24))
            ->selectRaw('EXTRACT(hour from timestamp) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        $hourlyData = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyData[] = [
                'hour' => $i,
                'events' => $hourlyEvents->get($i)?->count ?? 0,
            ];
        }

        return [
            'hourly_events' => $hourlyData,
            'event_types_distribution' => $this->getEventTypesDistribution($playerId, $days),
            'performance_timeline' => $this->getPerformanceTimeline($playerId, $days),
        ];
    }

    protected function getEventTypesDistribution(int $playerId, int $days): array
    {
        return PlayerLog::forPlayer($playerId)
            ->where('timestamp', '>=', now()->subDays($days))
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->get()
            ->map(function ($item) {
                $log = new PlayerLog(['event_type' => $item->event_type]);
                return [
                    'event_type' => $item->event_type,
                    'name' => $log->getEventTypeName(),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    protected function getPerformanceTimeline(int $playerId, int $days): array
    {
        return PlayerLog::forPlayer($playerId)
            ->performanceEvents()
            ->where('timestamp', '>=', now()->subDays($days))
            ->orderBy('timestamp')
            ->get()
            ->map(function ($log) {
                $data = $log->event_data ?? [];
                return [
                    'timestamp' => $log->timestamp->toISOString(),
                    'cpu_usage' => $data['cpu_usage'] ?? null,
                    'memory_usage' => $data['memory_usage'] ?? null,
                    'temperature' => $data['temperature'] ?? null,
                ];
            })
            ->toArray();
    }

    protected function getTenantOverview(int $tenantId, int $days): array
    {
        $baseQuery = PlayerLog::forTenant($tenantId)
            ->where('timestamp', '>=', now()->subDays($days));

        return [
            'total_events' => (clone $baseQuery)->count(),
            'unique_players' => (clone $baseQuery)->distinct('player_id')->count('player_id'),
            'error_rate' => $this->calculateErrorRate($tenantId, $days),
            'most_active_day' => $this->getMostActiveDay($tenantId, $days),
        ];
    }

    protected function calculateErrorRate(int $tenantId, int $days): float
    {
        $totalEvents = PlayerLog::forTenant($tenantId)
            ->where('timestamp', '>=', now()->subDays($days))
            ->count();

        $errorEvents = PlayerLog::forTenant($tenantId)
            ->errorEvents()
            ->where('timestamp', '>=', now()->subDays($days))
            ->count();

        return $totalEvents > 0 ? round(($errorEvents / $totalEvents) * 100, 2) : 0;
    }

    protected function getMostActiveDay(int $tenantId, int $days): ?array
    {
        $result = PlayerLog::forTenant($tenantId)
            ->where('timestamp', '>=', now()->subDays($days))
            ->selectRaw('DATE(timestamp) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('count', 'desc')
            ->first();

        return $result ? [
            'date' => $result->date,
            'event_count' => $result->count,
        ] : null;
    }

    protected function getTopPlayers(int $tenantId, int $days): array
    {
        return PlayerLog::forTenant($tenantId)
            ->where('timestamp', '>=', now()->subDays($days))
            ->with('player')
            ->selectRaw('player_id, COUNT(*) as event_count')
            ->groupBy('player_id')
            ->orderBy('event_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'player_id' => $item->player_id,
                    'player_name' => $item->player?->name ?? 'Unknown',
                    'event_count' => $item->event_count,
                ];
            })
            ->toArray();
    }

    protected function getTenantChartData(int $tenantId, int $days): array
    {
        // Events por dia nos últimos X dias
        $dailyEvents = PlayerLog::forTenant($tenantId)
            ->where('timestamp', '>=', now()->subDays($days))
            ->selectRaw('DATE(timestamp) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $dailyData = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dailyData[] = [
                'date' => $date,
                'events' => $dailyEvents->get($date)?->count ?? 0,
            ];
        }

        return [
            'daily_events' => $dailyData,
            'event_types_distribution' => $this->getEventTypesDistribution(null, $days),
        ];
    }
}