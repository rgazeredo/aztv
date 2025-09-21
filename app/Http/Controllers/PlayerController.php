<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Playlist;
use App\Models\PlayerLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $query = Player::withStatusData()
            ->forTenant($tenantId)
            ->withCount('logs');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('alias', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'online') {
                $query->online();
            } elseif ($status === 'offline') {
                $query->offline();
            }
        }

        if ($group = $request->get('group')) {
            $query->where('group', $group);
        }

        $players = $query->orderBy($request->get('sort', 'last_seen'), $request->get('direction', 'desc'))
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        $playersData = $players->getCollection()->map(function ($player) {
            return [
                'id' => $player->id,
                'name' => $player->name,
                'alias' => $player->alias,
                'location' => $player->location,
                'group' => $player->group,
                'status' => $player->getStatus(),
                'is_online' => $player->isOnline(),
                'last_seen' => $player->last_seen,
                'ip_address' => $player->ip_address,
                'app_version' => $player->app_version,
                'device_info' => $player->device_info,
                'playlists_count' => $player->playlists->count(),
                'logs_count' => $player->logs_count,
                'created_at' => $player->created_at,
            ];
        });

        $groups = Player::forTenant($tenantId)
            ->whereNotNull('group')
            ->distinct()
            ->pluck('group');

        return Inertia::render('Player/Index', [
            'players' => [
                'data' => $playersData,
                'current_page' => $players->currentPage(),
                'last_page' => $players->lastPage(),
                'per_page' => $players->perPage(),
                'total' => $players->total(),
            ],
            'filters' => $request->only(['search', 'status', 'group', 'sort', 'direction']),
            'groups' => $groups,
            'stats' => [
                'total_players' => Player::forTenant($tenantId)->count(),
                'online_players' => Player::activeWithTenant($tenantId)->count(),
                'offline_players' => Player::forTenant($tenantId)->offline()->count(),
            ],
            'performance_data' => $this->getPerformanceMetrics($tenantId),
        ]);
    }

    public function show(Player $player): Response
    {
        $this->authorize('view', $player);

        $player = Player::withFullData()->find($player->id);

        $activePlaylists = $player->getActivePlaylists();

        return Inertia::render('Player/Show', [
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'alias' => $player->alias,
                'location' => $player->location,
                'group' => $player->group,
                'status' => $player->getStatus(),
                'is_online' => $player->isOnline(),
                'last_seen' => $player->last_seen,
                'ip_address' => $player->ip_address,
                'app_version' => $player->app_version,
                'device_info' => $player->device_info,
                'activation_token' => $player->activation_token,
                'settings' => $player->settings,
                'created_at' => $player->created_at,
            ],
            'playlists' => $player->playlists->map(function ($playlist) {
                return [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'priority' => $playlist->pivot->priority,
                    'start_date' => $playlist->pivot->start_date,
                    'end_date' => $playlist->pivot->end_date,
                    'schedule_config' => $playlist->pivot->schedule_config,
                ];
            }),
            'active_playlists' => $activePlaylists->map(function ($playlist) {
                return [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'items_count' => $playlist->items()->count(),
                    'total_duration' => $playlist->getTotalDuration(),
                ];
            }),
            'recent_logs' => $player->logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'type' => $log->type,
                    'message' => $log->message,
                    'data' => $log->data,
                    'created_at' => $log->created_at,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $playlists = Playlist::forTenant($tenantId)->get();

        $tenant = auth()->user()->tenant;
        $plan = $tenant->getActivePlan();

        return Inertia::render('Player/Create', [
            'playlists' => $playlists,
            'limits' => [
                'players' => [
                    'current' => $tenant->getCurrentPlayerCount(),
                    'limit' => $tenant->getPlayerLimit(),
                    'percentage' => $tenant->getPlayerLimit() > 0 ?
                        round(($tenant->getCurrentPlayerCount() / $tenant->getPlayerLimit()) * 100, 1) : 0,
                    'is_at_limit' => $tenant->isAtPlayerLimit(),
                ],
                'plan_name' => $plan->name,
                'upgrade_url' => route('billing.plans'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'group' => 'nullable|string|max:100',
            'settings' => 'nullable|array',
            'playlists' => 'nullable|array',
            'playlists.*.playlist_id' => 'exists:playlists,id',
            'playlists.*.priority' => 'integer|min:1',
        ]);

        $tenant = auth()->user()->tenant;

        // Verificar limite de players
        if ($tenant->isAtPlayerLimit()) {
            $plan = $tenant->getActivePlan();
            $upgradeUrl = route('billing.plans');

            return back()->withErrors([
                'player_limit' => "Você atingiu o limite de {$plan->player_limit} player(s) do seu plano atual. " .
                    "<a href='{$upgradeUrl}' class='text-blue-600 hover:underline'>Clique aqui para fazer upgrade do seu plano</a>."
            ])->withInput();
        }

        $player = Player::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $validated['name'],
            'alias' => $validated['alias'],
            'location' => $validated['location'],
            'group' => $validated['group'],
            'activation_token' => Str::random(32),
            'settings' => $validated['settings'] ?? [],
        ]);

        if (!empty($validated['playlists'])) {
            foreach ($validated['playlists'] as $playlistData) {
                $player->playlists()->attach($playlistData['playlist_id'], [
                    'priority' => $playlistData['priority'] ?? 1,
                ]);
            }
        }

        PlayerLog::logInfo($player->id, 'Player criado via painel administrativo');

        return redirect()->route('players.show', $player)
            ->with('success', 'Player criado com sucesso!');
    }

    public function edit(Player $player): Response
    {
        $this->authorize('update', $player);

        $tenantId = auth()->user()->tenant_id;
        $playlists = Playlist::forTenant($tenantId)->get();

        return Inertia::render('Player/Edit', [
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'alias' => $player->alias,
                'location' => $player->location,
                'group' => $player->group,
                'settings' => $player->settings,
            ],
            'player_playlists' => $player->playlists->map(function ($playlist) {
                return [
                    'playlist_id' => $playlist->id,
                    'priority' => $playlist->pivot->priority,
                    'start_date' => $playlist->pivot->start_date,
                    'end_date' => $playlist->pivot->end_date,
                ];
            }),
            'playlists' => $playlists,
        ]);
    }

    public function update(Request $request, Player $player)
    {
        $this->authorize('update', $player);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'group' => 'nullable|string|max:100',
            'settings' => 'nullable|array',
            'playlists' => 'nullable|array',
            'playlists.*.playlist_id' => 'exists:playlists,id',
            'playlists.*.priority' => 'integer|min:1',
            'playlists.*.start_date' => 'nullable|date',
            'playlists.*.end_date' => 'nullable|date|after:start_date',
        ]);

        $player->update([
            'name' => $validated['name'],
            'alias' => $validated['alias'],
            'location' => $validated['location'],
            'group' => $validated['group'],
            'settings' => $validated['settings'] ?? [],
        ]);

        if (isset($validated['playlists'])) {
            $player->playlists()->detach();

            foreach ($validated['playlists'] as $playlistData) {
                $player->playlists()->attach($playlistData['playlist_id'], [
                    'priority' => $playlistData['priority'] ?? 1,
                    'start_date' => $playlistData['start_date'] ?? null,
                    'end_date' => $playlistData['end_date'] ?? null,
                ]);
            }
        }

        PlayerLog::logInfo($player->id, 'Player atualizado via painel administrativo');

        return redirect()->route('players.show', $player)
            ->with('success', 'Player atualizado com sucesso!');
    }

    public function destroy(Player $player)
    {
        $this->authorize('delete', $player);

        $player->delete();

        return redirect()->route('players.index')
            ->with('success', 'Player excluído com sucesso!');
    }

    private function getPerformanceMetrics($tenantId): array
    {
        $cacheKey = "performance_metrics_tenant_{$tenantId}";

        return Cache::remember($cacheKey, 300, function () use ($tenantId) {
            $query = Player::forTenant($tenantId);

            // Basic metrics
            $totalPlayers = $query->count();
            $onlinePlayers = $query->online()->count();
            $activePlayers = $query->where('last_seen', '>=', now()->subDay())->count();

            // Sync performance metrics
            $syncLogs = PlayerLog::whereHas('player', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->where('created_at', '>=', now()->subHour())
            ->where('message', 'like', '%sincroniz%')
            ->get();

            $totalSyncs = $syncLogs->count();
            $cacheHits = $syncLogs->filter(function ($log) {
                return isset($log->data['cache_hit']) && $log->data['cache_hit'] === true;
            })->count();

            $cacheHitRate = $totalSyncs > 0 ? round(($cacheHits / $totalSyncs) * 100, 1) : 0;

            // Query performance
            $avgQueryTime = $this->measureQueryPerformance($tenantId);

            return [
                'total_players' => $totalPlayers,
                'online_percentage' => $totalPlayers > 0 ? round(($onlinePlayers / $totalPlayers) * 100, 1) : 0,
                'active_24h' => $activePlayers,
                'sync_events_last_hour' => $totalSyncs,
                'cache_hit_rate' => $cacheHitRate,
                'avg_query_time_ms' => $avgQueryTime,
                'performance_score' => $this->calculatePerformanceScore($cacheHitRate, $avgQueryTime),
                'last_updated' => now()->toISOString(),
            ];
        });
    }

    private function measureQueryPerformance($tenantId): float
    {
        DB::enableQueryLog();
        $start = microtime(true);

        // Test common queries
        Player::withStatusData()->forTenant($tenantId)->take(5)->get();
        Player::activeWithTenant($tenantId)->take(5)->get();

        $time = (microtime(true) - $start) * 1000;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return round($time, 2);
    }

    private function calculatePerformanceScore($cacheHitRate, $avgQueryTime): string
    {
        $score = 0;

        // Cache hit rate score (50% weight)
        if ($cacheHitRate >= 80) $score += 50;
        elseif ($cacheHitRate >= 60) $score += 35;
        elseif ($cacheHitRate >= 40) $score += 20;
        else $score += 10;

        // Query time score (50% weight)
        if ($avgQueryTime <= 50) $score += 50;
        elseif ($avgQueryTime <= 100) $score += 35;
        elseif ($avgQueryTime <= 200) $score += 20;
        else $score += 10;

        if ($score >= 80) return 'excellent';
        elseif ($score >= 60) return 'good';
        elseif ($score >= 40) return 'fair';
        else return 'poor';
    }

    public function regenerateToken(Player $player)
    {
        $this->authorize('update', $player);

        $player->generateNewActivationToken();

        PlayerLog::logInfo($player->id, 'Token de ativação regenerado');

        return back()->with('success', 'Token de ativação regenerado com sucesso!');
    }

    public function restart(Player $player)
    {
        $this->authorize('update', $player);

        PlayerLog::logInfo($player->id, 'Comando de reinicialização enviado via painel administrativo');

        return back()->with('success', 'Comando de reinicialização enviado para o player!');
    }

    public function sendCommand(Request $request, Player $player)
    {
        $this->authorize('update', $player);

        $validated = $request->validate([
            'command' => 'required|string|in:restart,refresh_playlists,update_app,clear_cache',
            'parameters' => 'nullable|array',
        ]);

        $commands = [
            'restart' => 'Reinicializar player',
            'refresh_playlists' => 'Atualizar playlists',
            'update_app' => 'Atualizar aplicativo',
            'clear_cache' => 'Limpar cache',
        ];

        PlayerLog::logInfo(
            $player->id,
            "Comando enviado: {$commands[$validated['command']]}",
            $validated['parameters'] ?? []
        );

        return back()->with('success', "Comando '{$commands[$validated['command']]}' enviado com sucesso!");
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,restart,update_playlists',
            'player_ids' => 'required|array|min:1',
            'player_ids.*' => 'exists:players,id',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $players = Player::whereIn('id', $validated['player_ids'])
            ->forTenant($tenantId);

        switch ($validated['action']) {
            case 'delete':
                $players->delete();
                $message = 'Players excluídos com sucesso!';
                break;

            case 'restart':
                $players->get()->each(function ($player) {
                    PlayerLog::logInfo($player->id, 'Comando de reinicialização em massa enviado');
                });
                $message = 'Comando de reinicialização enviado para todos os players selecionados!';
                break;

            case 'update_playlists':
                $players->get()->each(function ($player) {
                    PlayerLog::logInfo($player->id, 'Comando de atualização de playlists em massa enviado');
                });
                $message = 'Comando de atualização de playlists enviado para todos os players selecionados!';
                break;
        }

        return back()->with('success', $message);
    }

    public function logs(Request $request, Player $player): Response
    {
        $this->authorize('view', $player);

        $query = $player->logs();

        if ($type = $request->get('type')) {
            $query->ofType($type);
        }

        if ($search = $request->get('search')) {
            $query->where('message', 'like', "%{$search}%");
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 25))
            ->withQueryString();

        $logTypes = PlayerLog::distinct()->pluck('type');

        return Inertia::render('Player/Logs', [
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'alias' => $player->alias,
            ],
            'logs' => $logs,
            'filters' => $request->only(['type', 'search']),
            'log_types' => $logTypes,
        ]);
    }

    public function analytics(Request $request, Player $player)
    {
        $this->authorize('view', $player);

        $period = $request->get('period', '7d');

        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $startDate = now()->subDays($days);

        $analytics = [
            'uptime' => $player->logs()
                ->where('type', 'heartbeat')
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as heartbeats')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            'media_played' => $player->logs()
                ->where('type', 'media_played')
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as plays')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            'errors' => $player->logs()
                ->where('type', 'error')
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as errors')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            'recent_activity' => $player->logs()
                ->whereIn('type', ['heartbeat', 'media_played', 'error', 'info'])
                ->where('created_at', '>=', now()->subHours(24))
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get(),
        ];

        return response()->json($analytics);
    }

    public function getCurrentQuote(Request $request)
    {
        $player = $this->authenticatePlayer($request);

        if (!$player) {
            return response()->json([
                'error' => 'Player not authenticated',
            ], 401);
        }

        $quoteService = app(\App\Services\QuoteService::class);
        $quote = $quoteService->getRandomQuote($player->tenant);

        if (!$quote) {
            return response()->json([
                'message' => 'No quotes available',
                'quote' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'quote' => [
                'id' => $quote->id,
                'text' => $quote->text,
                'author' => $quote->author,
                'category' => $quote->category,
                'display_duration' => $quote->display_duration,
                'created_at' => $quote->created_at,
            ],
            'timing' => [
                'display_duration' => $quote->display_duration,
                'transition_effect' => 'fade',
            ],
        ]);
    }

    public function getNextQuote(Request $request)
    {
        $player = $this->authenticatePlayer($request);

        if (!$player) {
            return response()->json([
                'error' => 'Player not authenticated',
            ], 401);
        }

        $currentQuoteId = $request->input('current_quote_id');
        $options = [
            'mode' => $request->input('mode', 'sequential'),
            'category' => $request->input('category'),
        ];

        $quoteService = app(\App\Services\QuoteService::class);
        $quote = $quoteService->getNextQuote($player->tenant, $currentQuoteId, $options);

        if (!$quote) {
            return response()->json([
                'message' => 'No more quotes available',
                'quote' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'quote' => [
                'id' => $quote->id,
                'text' => $quote->text,
                'author' => $quote->author,
                'category' => $quote->category,
                'display_duration' => $quote->display_duration,
                'created_at' => $quote->created_at,
            ],
            'timing' => [
                'display_duration' => $quote->display_duration,
                'transition_effect' => 'fade',
            ],
        ]);
    }

    private function authenticatePlayer(Request $request): ?Player
    {
        $token = $request->header('X-Player-Token') ?? $request->input('token');

        if (!$token) {
            return null;
        }

        return Player::where('token', $token)->first();
    }

    public function getCurrencyRates(Request $request)
    {
        $player = $this->authenticatePlayer($request);

        if (!$player) {
            return response()->json([
                'error' => 'Player not authenticated',
            ], 401);
        }

        $currencyService = app(\App\Services\CurrencyService::class);
        $currencies = $request->input('currencies', ['USD', 'EUR', 'BTC']);

        if (is_string($currencies)) {
            $currencies = explode(',', $currencies);
        }

        $rates = $currencyService->getStoredRates($currencies);
        $healthCheck = $currencyService->isServiceHealthy();

        return response()->json([
            'success' => true,
            'rates' => $rates,
            'metadata' => [
                'currencies_requested' => $currencies,
                'currencies_found' => array_keys($rates),
                'last_updated' => now()->toISOString(),
                'service_healthy' => $healthCheck,
                'cache_ttl' => 300, // 5 minutes
            ],
        ]);
    }
}