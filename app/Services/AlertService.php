<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\Player;
use App\Models\PlayerLog;
use App\Models\Tenant;
use App\Mail\PlayerOfflineAlert;
use App\Mail\PlaybackErrorAlert;
use App\Mail\StorageLimitAlert;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AlertService
{
    public function checkAllAlerts(): array
    {
        $results = [];
        $tenants = Tenant::active()->with('alertRules')->get();

        foreach ($tenants as $tenant) {
            $tenantResults = $this->checkTenantAlerts($tenant);
            if (!empty($tenantResults)) {
                $results[$tenant->id] = $tenantResults;
            }
        }

        return $results;
    }

    public function checkTenantAlerts(Tenant $tenant): array
    {
        $results = [];
        $alertRules = $tenant->alertRules()->ready()->get();

        foreach ($alertRules as $rule) {
            try {
                $alertData = $this->checkAlertRule($rule);
                if ($alertData) {
                    $this->sendAlert($rule, $alertData);
                    $rule->markAsTriggered();
                    $results[] = [
                        'rule_id' => $rule->id,
                        'type' => $rule->type,
                        'triggered' => true,
                        'data' => $alertData,
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Error checking alert rule {$rule->id}: " . $e->getMessage());
                $results[] = [
                    'rule_id' => $rule->id,
                    'type' => $rule->type,
                    'triggered' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    protected function checkAlertRule(AlertRule $rule): ?array
    {
        return match($rule->type) {
            AlertRule::TYPE_PLAYER_OFFLINE => $this->checkPlayerOffline($rule),
            AlertRule::TYPE_PLAYBACK_ERROR => $this->checkPlaybackErrors($rule),
            AlertRule::TYPE_STORAGE_LIMIT => $this->checkStorageLimit($rule),
            default => null,
        };
    }

    protected function checkPlayerOffline(AlertRule $rule): ?array
    {
        $thresholdMinutes = $rule->threshold ?? 15;
        $cutoffTime = now()->subMinutes($thresholdMinutes);

        $query = Player::forTenant($rule->tenant_id)
            ->where(function ($q) use ($cutoffTime) {
                $q->where('last_seen', '<', $cutoffTime)
                  ->orWhereNull('last_seen');
            });

        // Apply conditions if specified
        if (!empty($rule->condition['player_ids'])) {
            $query->whereIn('id', $rule->condition['player_ids']);
        }

        if (!empty($rule->condition['include_groups'])) {
            $query->whereIn('group', $rule->condition['include_groups']);
        }

        $offlinePlayers = $query->get();

        if ($offlinePlayers->isEmpty()) {
            return null;
        }

        return [
            'offline_players' => $offlinePlayers->map(function ($player) use ($cutoffTime) {
                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'alias' => $player->alias,
                    'group' => $player->group,
                    'location' => $player->location,
                    'last_seen' => $player->last_seen,
                    'offline_duration' => $player->last_seen ?
                        $player->last_seen->diffForHumans($cutoffTime, true) : 'Nunca conectado',
                ];
            })->toArray(),
            'threshold_minutes' => $thresholdMinutes,
            'total_offline' => $offlinePlayers->count(),
        ];
    }

    protected function checkPlaybackErrors(AlertRule $rule): ?array
    {
        $thresholdErrors = $rule->threshold ?? 5;
        $oneHourAgo = now()->subHour();

        $query = PlayerLog::forTenant($rule->tenant_id)
            ->errorEvents()
            ->where('timestamp', '>=', $oneHourAgo);

        // Apply conditions if specified
        if (!empty($rule->condition['player_ids'])) {
            $query->whereIn('player_id', $rule->condition['player_ids']);
        }

        if (!empty($rule->condition['include_groups'])) {
            $query->whereHas('player', function ($q) use ($rule) {
                $q->whereIn('group', $rule->condition['include_groups']);
            });
        }

        $errorLogs = $query->with(['player', 'mediaFile'])->get();
        $errorCount = $errorLogs->count();

        if ($errorCount < $thresholdErrors) {
            return null;
        }

        // Group errors by player
        $errorsByPlayer = $errorLogs->groupBy('player_id')->map(function ($logs) {
            $player = $logs->first()->player;
            return [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'player_alias' => $player->alias,
                'error_count' => $logs->count(),
                'latest_errors' => $logs->take(3)->map(function ($log) {
                    return [
                        'timestamp' => $log->timestamp,
                        'event_type' => $log->event_type,
                        'event_data' => $log->event_data,
                        'media_file' => $log->mediaFile ? [
                            'filename' => $log->mediaFile->filename,
                            'original_name' => $log->mediaFile->original_name,
                        ] : null,
                    ];
                })->toArray(),
            ];
        })->toArray();

        return [
            'total_errors' => $errorCount,
            'threshold_errors' => $thresholdErrors,
            'time_period' => '1 hora',
            'errors_by_player' => $errorsByPlayer,
            'most_affected_player' => collect($errorsByPlayer)->sortByDesc('error_count')->first(),
        ];
    }

    protected function checkStorageLimit(AlertRule $rule): ?array
    {
        $tenant = $rule->tenant;
        $thresholdPercentage = $rule->threshold ?? 85;

        $currentUsageGb = $tenant->getCurrentStorageUsage();
        $limitGb = $tenant->getStorageLimitGb();

        if ($limitGb <= 0) {
            return null; // No limit set
        }

        $usagePercentage = ($currentUsageGb / $limitGb) * 100;

        if ($usagePercentage < $thresholdPercentage) {
            return null;
        }

        // Get top files by size
        $largestFiles = $tenant->mediaFiles()
            ->orderBy('size', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filename' => $file->filename,
                    'original_name' => $file->original_name,
                    'size' => $file->size,
                    'formatted_size' => $file->getFormattedSize(),
                    'created_at' => $file->created_at,
                ];
            });

        return [
            'current_usage_gb' => round($currentUsageGb, 2),
            'limit_gb' => $limitGb,
            'usage_percentage' => round($usagePercentage, 1),
            'threshold_percentage' => $thresholdPercentage,
            'remaining_gb' => round($limitGb - $currentUsageGb, 2),
            'largest_files' => $largestFiles->toArray(),
            'plan_name' => $tenant->getActivePlan()?->name ?? 'Unknown',
        ];
    }

    protected function sendAlert(AlertRule $rule, array $alertData): void
    {
        try {
            $mailable = $this->createMailable($rule, $alertData);

            if ($mailable) {
                foreach ($rule->recipients as $email) {
                    Mail::to($email)->queue($mailable);
                }

                Log::info("Alert sent for rule {$rule->id} to " . implode(', ', $rule->recipients));
            }
        } catch (\Exception $e) {
            Log::error("Failed to send alert for rule {$rule->id}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function createMailable(AlertRule $rule, array $alertData)
    {
        return match($rule->type) {
            AlertRule::TYPE_PLAYER_OFFLINE => new PlayerOfflineAlert($rule, $alertData),
            AlertRule::TYPE_PLAYBACK_ERROR => new PlaybackErrorAlert($rule, $alertData),
            AlertRule::TYPE_STORAGE_LIMIT => new StorageLimitAlert($rule, $alertData),
            default => null,
        };
    }

    public function testAlert(AlertRule $rule): array
    {
        // Generate test data for each alert type
        $testData = match($rule->type) {
            AlertRule::TYPE_PLAYER_OFFLINE => [
                'offline_players' => [
                    [
                        'id' => 1,
                        'name' => 'Player Teste',
                        'alias' => 'teste-01',
                        'group' => 'loja-matriz',
                        'location' => 'Sala Principal',
                        'last_seen' => now()->subMinutes(30),
                        'offline_duration' => '30 minutos',
                    ]
                ],
                'threshold_minutes' => $rule->threshold ?? 15,
                'total_offline' => 1,
            ],
            AlertRule::TYPE_PLAYBACK_ERROR => [
                'total_errors' => 10,
                'threshold_errors' => $rule->threshold ?? 5,
                'time_period' => '1 hora',
                'errors_by_player' => [
                    [
                        'player_name' => 'Player Teste',
                        'error_count' => 10,
                        'latest_errors' => [
                            [
                                'timestamp' => now()->subMinutes(5),
                                'event_type' => 'MEDIA_ERROR',
                                'event_data' => ['message' => 'Falha ao carregar mídia'],
                            ]
                        ],
                    ]
                ],
                'most_affected_player' => [
                    'player_name' => 'Player Teste',
                    'error_count' => 10,
                ],
            ],
            AlertRule::TYPE_STORAGE_LIMIT => [
                'current_usage_gb' => 8.5,
                'limit_gb' => 10,
                'usage_percentage' => 85,
                'threshold_percentage' => $rule->threshold ?? 85,
                'remaining_gb' => 1.5,
                'plan_name' => 'Plano Básico',
                'largest_files' => [
                    [
                        'filename' => 'video_promocional.mp4',
                        'formatted_size' => '2.1 GB',
                    ]
                ],
            ],
            default => [],
        };

        try {
            $this->sendAlert($rule, $testData);
            return [
                'success' => true,
                'message' => 'Email de teste enviado com sucesso',
                'test_data' => $testData,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao enviar email de teste: ' . $e->getMessage(),
                'test_data' => $testData,
            ];
        }
    }

    public function getAlertStatistics(int $tenantId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $alertRules = AlertRule::forTenant($tenantId)->get();
        $triggeredCount = $alertRules->where('last_triggered_at', '>=', $startDate)->count();

        return [
            'total_rules' => $alertRules->count(),
            'active_rules' => $alertRules->where('is_active', true)->count(),
            'triggered_last_30d' => $triggeredCount,
            'rules_by_type' => $alertRules->groupBy('type')->map->count(),
            'last_triggered' => $alertRules->whereNotNull('last_triggered_at')
                ->sortByDesc('last_triggered_at')
                ->first()?->last_triggered_at,
        ];
    }
}