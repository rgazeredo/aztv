<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerLog;
use App\Models\ApkVersion;
use App\Models\ContentModule;
use App\Services\PlayerCacheService;
use App\Services\SyncCacheService;
use App\Services\PlayerLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PlayerApiController extends Controller
{
    private PlayerCacheService $playerCacheService;
    private SyncCacheService $syncCacheService;
    private PlayerLogService $playerLogService;

    public function __construct(
        PlayerCacheService $playerCacheService,
        SyncCacheService $syncCacheService,
        PlayerLogService $playerLogService
    ) {
        $this->playerCacheService = $playerCacheService;
        $this->syncCacheService = $syncCacheService;
        $this->playerLogService = $playerLogService;
    }

    public function authenticate(Request $request)
    {
        $validated = $request->validate([
            'activation_token' => 'required|string',
            'device_info' => 'nullable|array',
            'app_version' => 'nullable|string',
        ]);

        $player = Player::where('activation_token', $validated['activation_token'])->first();

        if (!$player) {
            return response()->json([
                'success' => false,
                'message' => 'Token de ativação inválido',
            ], 401);
        }

        $player->update([
            'device_info' => $validated['device_info'] ?? $player->device_info,
            'app_version' => $validated['app_version'] ?? $player->app_version,
            'ip_address' => $request->ip(),
            'last_seen' => now(),
        ]);

        // Cache player configuration after authentication
        $this->playerCacheService->cachePlayerConfig($player);

        // Cache sync timestamp
        $this->syncCacheService->cacheSyncTimestamp($player->id, now());

        PlayerLog::logInfo($player->id, 'Player autenticado com sucesso', [
            'app_version' => $validated['app_version'],
            'ip_address' => $request->ip(),
        ]);

        $apiToken = Hash::make($player->id . time());

        cache()->put("player_token_{$player->id}", $apiToken, now()->addDays(30));

        return response()->json([
            'success' => true,
            'message' => 'Autenticado com sucesso',
            'data' => [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'api_token' => $apiToken,
                'settings' => $player->settings,
                'tenant' => [
                    'id' => $player->tenant->id,
                    'name' => $player->tenant->name,
                ],
            ],
        ]);
    }

    public function heartbeat(Request $request)
    {
        $player = $this->getAuthenticatedPlayerForStatus($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'status' => 'nullable|string',
            'current_media' => 'nullable|string',
            'system_info' => 'nullable|array',
        ]);

        $player->updateLastSeen();

        PlayerLog::logHeartbeat($player->id, 'Heartbeat recebido', [
            'status' => $validated['status'] ?? 'unknown',
            'current_media' => $validated['current_media'],
            'system_info' => $validated['system_info'] ?? [],
        ]);

        $activePlaylists = $player->getActivePlaylists();

        return response()->json([
            'success' => true,
            'data' => [
                'server_time' => now()->toISOString(),
                'commands' => $this->getPendingCommands($player),
                'playlists_updated' => $this->checkPlaylistsUpdated($player),
                'app_update_available' => $this->checkAppUpdateAvailable($player),
            ],
        ]);
    }

    public function getPlaylists(Request $request)
    {
        $player = $this->getAuthenticatedPlayerForSync($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        // Try to get cached playlists first
        $cachedPlaylist = $this->playerCacheService->getCachedActivePlaylist($player->id);

        if ($cachedPlaylist) {
            PlayerLog::logInfo($player->id, 'Playlists sincronizadas (cache)', [
                'cache_hit' => true,
                'playlist_id' => $cachedPlaylist['id'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'playlists' => [$cachedPlaylist],
                    'last_updated' => $cachedPlaylist['cached_at'],
                    'from_cache' => true,
                ],
            ]);
        }

        $activePlaylists = $player->getActivePlaylists();

        $playlistsData = $activePlaylists->map(function ($playlist) {
            return [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'loop_enabled' => $playlist->loop_enabled,
                'priority' => $playlist->pivot->priority,
                'schedule_config' => $playlist->pivot->schedule_config,
                'items' => $playlist->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'order' => $item->order,
                        'display_time' => $item->getDisplayTime(),
                        'media_file' => [
                            'id' => $item->mediaFile->id,
                            'filename' => $item->mediaFile->filename,
                            'url' => $item->mediaFile->getUrl(),
                            'mime_type' => $item->mediaFile->mime_type,
                            'duration' => $item->mediaFile->duration,
                            'thumbnail_url' => $item->mediaFile->getThumbnailUrl(),
                        ],
                    ];
                }),
            ];
        });

        // Cache the first active playlist if exists
        if ($activePlaylists->isNotEmpty()) {
            $firstPlaylist = $activePlaylists->first();
            $this->playerCacheService->cacheActivePlaylist($player->id, $firstPlaylist);
        }

        PlayerLog::logInfo($player->id, 'Playlists sincronizadas (database)', [
            'playlists_count' => $playlistsData->count(),
            'cache_hit' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'playlists' => $playlistsData,
                'last_updated' => now()->toISOString(),
                'from_cache' => false,
            ],
        ]);
    }

    public function syncDelta(Request $request)
    {
        $player = $this->getAuthenticatedPlayerForSync($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'last_sync' => 'nullable|date',
        ]);

        $lastSync = $validated['last_sync'] ? Carbon::parse($validated['last_sync']) : null;

        // Try to get sync data from cache first
        $cachedSyncData = $this->syncCacheService->getSyncDataFromCache($player->id, $lastSync);

        if (!empty($cachedSyncData)) {
            PlayerLog::logInfo($player->id, 'Sync delta (cache)', [
                'cache_hit' => true,
                'last_sync' => $lastSync?->toISOString(),
                'changes_count' => count($cachedSyncData['sync_data']['changes'] ?? []),
            ]);

            return response()->json([
                'success' => true,
                'data' => $cachedSyncData['sync_data'],
                'from_cache' => true,
            ]);
        }

        // Generate fresh sync delta
        $syncDelta = $this->syncCacheService->generateSyncDelta($player->id, $lastSync);

        // Update sync timestamp
        $this->syncCacheService->cacheSyncTimestamp($player->id, now());

        PlayerLog::logInfo($player->id, 'Sync delta (generated)', [
            'cache_hit' => false,
            'last_sync' => $lastSync?->toISOString(),
            'changes_count' => count($syncDelta['changes'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            'data' => $syncDelta,
            'from_cache' => false,
        ]);
    }

    public function getContentModules(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $modules = ContentModule::forTenant($player->tenant_id)
            ->enabled()
            ->get()
            ->filter(function ($module) {
                return $module->hasRequiredSettings();
            });

        $modulesData = $modules->map(function ($module) {
            return [
                'type' => $module->type,
                'display_name' => $module->getDisplayName(),
                'settings' => $module->settings,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'modules' => $modulesData,
                'last_updated' => now()->toISOString(),
            ],
        ]);
    }

    public function logMediaPlayed(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'media_file_id' => 'required|exists:media_files,id',
            'playlist_id' => 'required|exists:playlists,id',
            'duration_played' => 'nullable|integer|min:0',
            'completed' => 'boolean',
        ]);

        PlayerLog::logMediaPlayed($player->id, 'Mídia reproduzida', [
            'media_file_id' => $validated['media_file_id'],
            'playlist_id' => $validated['playlist_id'],
            'duration_played' => $validated['duration_played'] ?? 0,
            'completed' => $validated['completed'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Log de reprodução registrado',
        ]);
    }

    public function logError(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'error_type' => 'required|string',
            'error_message' => 'required|string',
            'stack_trace' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        PlayerLog::logError($player->id, $validated['error_message'], [
            'error_type' => $validated['error_type'],
            'stack_trace' => $validated['stack_trace'],
            'context' => $validated['context'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Erro registrado',
        ]);
    }

    public function checkUpdate(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $currentVersion = $request->get('current_version', $player->app_version);
        $activeVersion = ApkVersion::getActiveVersion();

        if (!$activeVersion) {
            return response()->json([
                'success' => true,
                'update_available' => false,
                'message' => 'Nenhuma versão disponível',
            ]);
        }

        $updateAvailable = version_compare($activeVersion->version, $currentVersion, '>');

        $response = [
            'success' => true,
            'update_available' => $updateAvailable,
            'current_version' => $currentVersion,
            'latest_version' => $activeVersion->version,
        ];

        if ($updateAvailable) {
            $response['download_url'] = $activeVersion->getDownloadUrl();
            $response['changelog'] = $activeVersion->changelog;
            $response['file_size'] = $activeVersion->getFileSize();
            $response['force_update'] = $activeVersion->settings['force_update'] ?? false;
        }

        return response()->json($response);
    }

    public function downloadUpdate(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $activeVersion = ApkVersion::getActiveVersion();

        if (!$activeVersion) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma versão disponível para download',
            ], 404);
        }

        $activeVersion->incrementDownloadCount();

        PlayerLog::logInfo($player->id, 'Download de atualização iniciado', [
            'version' => $activeVersion->version,
            'file_size' => $activeVersion->getFileSize(),
        ]);

        return response()->json([
            'success' => true,
            'download_url' => $activeVersion->getDownloadUrl(),
            'version' => $activeVersion->version,
            'file_size' => $activeVersion->getFileSize(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        $currentSettings = $player->settings ?? [];
        $newSettings = array_merge($currentSettings, $validated['settings']);

        $player->update(['settings' => $newSettings]);

        PlayerLog::logInfo($player->id, 'Configurações atualizadas via API', [
            'updated_settings' => array_keys($validated['settings']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configurações atualizadas',
            'settings' => $newSettings,
        ]);
    }

    public function getCommands(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $commands = $this->getPendingCommands($player);

        return response()->json([
            'success' => true,
            'commands' => $commands,
        ]);
    }

    public function confirmCommand(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'command_id' => 'required|string',
            'status' => 'required|in:executed,failed',
            'message' => 'nullable|string',
        ]);

        cache()->forget("player_command_{$player->id}_{$validated['command_id']}");

        PlayerLog::logInfo($player->id, "Comando {$validated['status']}: {$validated['command_id']}", [
            'command_id' => $validated['command_id'],
            'status' => $validated['status'],
            'message' => $validated['message'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status do comando confirmado',
        ]);
    }

    public function logEvents(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'event_type' => 'required|string',
            'event_data' => 'nullable|array',
            'media_file_id' => 'nullable|exists:media_files,id',
            'timestamp' => 'nullable|date',
        ]);

        $result = $this->playerLogService->logMediaEvent(
            $player->id,
            $validated['event_type'],
            $validated['media_file_id'] ?? null,
            $validated['event_data'] ?? []
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded or invalid event type',
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event logged successfully',
            'log_id' => $result->id,
        ]);
    }

    public function logBatch(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'events' => 'required|array|min:1|max:100',
            'events.*.event_type' => 'required|string',
            'events.*.event_data' => 'nullable|array',
            'events.*.media_file_id' => 'nullable|exists:media_files,id',
            'events.*.timestamp' => 'nullable|date',
        ]);

        $results = $this->playerLogService->logBatch($player->id, $validated['events']);

        return response()->json([
            'success' => true,
            'message' => 'Batch logged successfully',
            'processed' => count($results),
            'total' => count($validated['events']),
        ]);
    }

    public function logMediaStart(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'media_file_id' => 'required|exists:media_files,id',
            'playlist_id' => 'nullable|exists:playlists,id',
            'metadata' => 'nullable|array',
        ]);

        $metadata = array_merge([
            'playlist_id' => $validated['playlist_id'],
        ], $validated['metadata'] ?? []);

        $result = $this->playerLogService->logMediaStart(
            $player->id,
            $validated['media_file_id'],
            $metadata
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded',
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'Media start logged',
            'log_id' => $result->id,
        ]);
    }

    public function logMediaEnd(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'media_file_id' => 'required|exists:media_files,id',
            'duration_played' => 'nullable|integer|min:0',
            'completed' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $metadata = array_merge([
            'duration_played' => $validated['duration_played'],
            'completed' => $validated['completed'] ?? false,
        ], $validated['metadata'] ?? []);

        $result = $this->playerLogService->logMediaEnd(
            $player->id,
            $validated['media_file_id'],
            $metadata
        );

        return response()->json([
            'success' => true,
            'message' => 'Media end logged',
            'log_id' => $result?->id,
        ]);
    }

    public function logMediaError(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'media_file_id' => 'nullable|exists:media_files,id',
            'error_type' => 'required|string',
            'error_message' => 'required|string',
            'error_code' => 'nullable|string',
            'stack_trace' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        $errorDetails = [
            'message' => $validated['error_message'],
            'code' => $validated['error_code'],
            'stack_trace' => $validated['stack_trace'],
            'context' => $validated['context'] ?? [],
        ];

        $result = $this->playerLogService->logMediaError(
            $player->id,
            $validated['media_file_id'],
            $validated['error_type'],
            $errorDetails
        );

        return response()->json([
            'success' => true,
            'message' => 'Media error logged',
            'log_id' => $result?->id,
        ]);
    }

    public function logConnectivityError(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'error_type' => 'required|string',
            'error_message' => 'required|string',
            'network_info' => 'nullable|array',
            'retry_count' => 'nullable|integer|min:0',
            'context' => 'nullable|array',
        ]);

        $errorDetails = [
            'message' => $validated['error_message'],
            'network_info' => $validated['network_info'],
            'retry_count' => $validated['retry_count'] ?? 0,
            'context' => $validated['context'] ?? [],
        ];

        $result = $this->playerLogService->logConnectivityError(
            $player->id,
            $validated['error_type'],
            $errorDetails
        );

        return response()->json([
            'success' => true,
            'message' => 'Connectivity error logged',
            'log_id' => $result?->id,
        ]);
    }

    public function logPerformanceMetrics(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'cpu_usage' => 'nullable|numeric|min:0|max:100',
            'memory_usage' => 'nullable|integer|min:0',
            'memory_total' => 'nullable|integer|min:0',
            'storage_usage' => 'nullable|integer|min:0',
            'storage_total' => 'nullable|integer|min:0',
            'temperature' => 'nullable|numeric',
            'network_latency' => 'nullable|integer|min:0',
            'fps' => 'nullable|numeric|min:0',
            'battery_level' => 'nullable|integer|min:0|max:100',
            'additional_metrics' => 'nullable|array',
        ]);

        $metrics = array_merge(
            array_filter($validated, function ($key) {
                return $key !== 'additional_metrics';
            }, ARRAY_FILTER_USE_KEY),
            $validated['additional_metrics'] ?? []
        );

        $result = $this->playerLogService->logPerformanceMetric($player->id, $metrics);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded',
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'Performance metrics logged',
            'log_id' => $result->id,
        ]);
    }

    public function enhancedHeartbeat(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'status' => 'nullable|string',
            'current_media' => 'nullable|string',
            'app_version' => 'nullable|string',
            'android_version' => 'nullable|string',
            'device_model' => 'nullable|string',
            'system_info' => 'nullable|array',
        ]);

        $player->updateLastSeen();

        $systemInfo = array_merge([
            'status' => $validated['status'] ?? 'unknown',
            'current_media' => $validated['current_media'],
            'app_version' => $validated['app_version'],
            'android_version' => $validated['android_version'],
            'device_model' => $validated['device_model'],
        ], $validated['system_info'] ?? []);

        $result = $this->playerLogService->logHeartbeat($player->id, $systemInfo);

        $activePlaylists = $player->getActivePlaylists();

        return response()->json([
            'success' => true,
            'data' => [
                'server_time' => now()->toISOString(),
                'commands' => $this->getPendingCommands($player),
                'playlists_updated' => $this->checkPlaylistsUpdated($player),
                'app_update_available' => $this->checkAppUpdateAvailable($player),
                'heartbeat_logged' => $result !== null,
            ],
        ]);
    }

    private function getAuthenticatedPlayer(Request $request, string $context = 'basic'): ?Player
    {
        $playerId = $request->header('X-Player-ID');
        $apiToken = $request->header('X-API-Token');

        if (!$playerId || !$apiToken) {
            return null;
        }

        $cachedToken = cache()->get("player_token_{$playerId}");

        if (!$cachedToken || $cachedToken !== $apiToken) {
            return null;
        }

        // Use different eager loading based on context
        switch ($context) {
            case 'sync':
                return Player::withSyncData()->find($playerId);
            case 'status':
                return Player::withStatusData()->find($playerId);
            case 'full':
                return Player::withFullData()->find($playerId);
            default:
                return Player::find($playerId);
        }
    }

    private function getAuthenticatedPlayerForSync(Request $request): ?Player
    {
        return $this->getAuthenticatedPlayer($request, 'sync');
    }

    private function getAuthenticatedPlayerForStatus(Request $request): ?Player
    {
        return $this->getAuthenticatedPlayer($request, 'status');
    }

    private function unauthorizedResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Não autorizado',
            'error_code' => 'UNAUTHORIZED',
        ], 401);
    }

    private function getPendingCommands(Player $player): array
    {
        $commands = [];

        $cachedCommands = cache()->get("player_commands_{$player->id}", []);

        foreach ($cachedCommands as $commandId => $command) {
            $commands[] = [
                'id' => $commandId,
                'type' => $command['type'],
                'parameters' => $command['parameters'] ?? [],
                'created_at' => $command['created_at'],
            ];
        }

        return $commands;
    }

    private function checkPlaylistsUpdated(Player $player): bool
    {
        $lastUpdate = cache()->get("player_playlists_updated_{$player->id}");

        if (!$lastUpdate) {
            return false;
        }

        $playlistsLastModified = $player->playlists()
            ->max('updated_at');

        return $playlistsLastModified && $playlistsLastModified > $lastUpdate;
    }

    private function checkAppUpdateAvailable(Player $player): bool
    {
        $currentVersion = $player->app_version;
        $activeVersion = ApkVersion::getActiveVersion();

        if (!$activeVersion || !$currentVersion) {
            return false;
        }

        return version_compare($activeVersion->version, $currentVersion, '>');
    }
}