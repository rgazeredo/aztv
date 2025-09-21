<?php

namespace App\Jobs;

use App\Models\PlaylistSchedule;
use App\Models\Player;
use App\Models\Playlist;
use App\Models\Tenant;
use App\Services\ScheduleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class RecurringPlaylistScheduler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;

    private ScheduleService $scheduleService;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $tenantId = null,
        public bool $force = false
    ) {
        $this->onQueue('scheduler');
    }

    /**
     * Execute the job.
     */
    public function handle(ScheduleService $scheduleService): void
    {
        $this->scheduleService = $scheduleService;

        try {
            Log::info('Starting RecurringPlaylistScheduler job', [
                'tenant_id' => $this->tenantId,
                'force' => $this->force,
                'timestamp' => now()->toDateTimeString(),
            ]);

            $currentDateTime = now();

            // Get tenants to process
            $tenants = $this->tenantId
                ? Tenant::where('id', $this->tenantId)->get()
                : Tenant::all();

            $totalActivations = 0;
            $totalDeactivations = 0;
            $totalErrors = 0;

            foreach ($tenants as $tenant) {
                $result = $this->processTenantSchedules($tenant, $currentDateTime);

                $totalActivations += $result['activations'];
                $totalDeactivations += $result['deactivations'];
                $totalErrors += $result['errors'];
            }

            Log::info('RecurringPlaylistScheduler job completed', [
                'tenants_processed' => $tenants->count(),
                'total_activations' => $totalActivations,
                'total_deactivations' => $totalDeactivations,
                'total_errors' => $totalErrors,
                'execution_time' => microtime(true) - LARAVEL_START,
            ]);

        } catch (Exception $e) {
            Log::error('RecurringPlaylistScheduler job failed: ' . $e->getMessage(), [
                'tenant_id' => $this->tenantId,
                'exception' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Process schedules for a specific tenant
     */
    private function processTenantSchedules(Tenant $tenant, Carbon $currentDateTime): array
    {
        $activations = 0;
        $deactivations = 0;
        $errors = 0;

        try {
            // Get all active schedules for current datetime
            $activeSchedules = $this->scheduleService->getActiveSchedulesForDateTime(
                $currentDateTime,
                $tenant->id
            );

            // Get highest priority schedule (if any)
            $highestPrioritySchedule = $activeSchedules->first();

            // Get all players for this tenant
            $players = Player::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->get();

            if ($players->isEmpty()) {
                Log::debug("No active players found for tenant {$tenant->id}");
                return compact('activations', 'deactivations', 'errors');
            }

            foreach ($players as $player) {
                try {
                    $result = $this->processPlayerSchedule($player, $highestPrioritySchedule, $currentDateTime);

                    if ($result['action'] === 'activated') {
                        $activations++;
                    } elseif ($result['action'] === 'deactivated') {
                        $deactivations++;
                    }

                } catch (Exception $e) {
                    $errors++;
                    Log::error("Failed to process schedule for player {$player->id}: " . $e->getMessage(), [
                        'player_id' => $player->id,
                        'tenant_id' => $tenant->id,
                        'exception' => $e->getTraceAsString(),
                    ]);
                }
            }

            Log::info("Processed schedules for tenant {$tenant->id}", [
                'tenant_name' => $tenant->name,
                'active_schedules' => $activeSchedules->count(),
                'players_processed' => $players->count(),
                'activations' => $activations,
                'deactivations' => $deactivations,
                'errors' => $errors,
            ]);

        } catch (Exception $e) {
            $errors++;
            Log::error("Failed to process tenant {$tenant->id}: " . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'exception' => $e->getTraceAsString(),
            ]);
        }

        return compact('activations', 'deactivations', 'errors');
    }

    /**
     * Process schedule for a specific player
     */
    private function processPlayerSchedule(Player $player, ?PlaylistSchedule $activeSchedule, Carbon $currentDateTime): array
    {
        $currentActivePlaylist = $this->getCurrentActivePlaylist($player);
        $targetPlaylist = null;
        $action = 'none';

        if ($activeSchedule) {
            // There's an active schedule, use its playlist
            $targetPlaylist = $activeSchedule->playlist;

            // Check if we need to activate this playlist
            if (!$currentActivePlaylist || $currentActivePlaylist->id !== $targetPlaylist->id) {
                $this->activatePlaylistOnPlayer($player, $targetPlaylist, $activeSchedule);
                $action = 'activated';

                Log::info("Activated playlist on player", [
                    'player_id' => $player->id,
                    'player_name' => $player->name,
                    'playlist_id' => $targetPlaylist->id,
                    'playlist_name' => $targetPlaylist->name,
                    'schedule_id' => $activeSchedule->id,
                    'schedule_name' => $activeSchedule->name,
                    'priority' => $activeSchedule->priority,
                    'timestamp' => $currentDateTime->toDateTimeString(),
                ]);
            }
        } else {
            // No active schedule, use default playlist
            $defaultPlaylist = $this->getDefaultPlaylist($player->tenant_id);

            if ($defaultPlaylist && (!$currentActivePlaylist || $currentActivePlaylist->id !== $defaultPlaylist->id)) {
                $this->activatePlaylistOnPlayer($player, $defaultPlaylist);
                $action = 'deactivated';

                Log::info("Activated default playlist on player", [
                    'player_id' => $player->id,
                    'player_name' => $player->name,
                    'playlist_id' => $defaultPlaylist->id,
                    'playlist_name' => $defaultPlaylist->name,
                    'reason' => 'no_active_schedule',
                    'timestamp' => $currentDateTime->toDateTimeString(),
                ]);
            }
        }

        return [
            'action' => $action,
            'current_playlist' => $currentActivePlaylist?->id,
            'target_playlist' => $targetPlaylist?->id ?? $this->getDefaultPlaylist($player->tenant_id)?->id,
        ];
    }

    /**
     * Get current active playlist for player
     */
    private function getCurrentActivePlaylist(Player $player): ?Playlist
    {
        return $player->playlists()
            ->where(function ($query) {
                $query->whereNull('player_playlists.end_date')
                      ->orWhere('player_playlists.end_date', '>=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('player_playlists.start_date')
                      ->orWhere('player_playlists.start_date', '<=', now()->toDateString());
            })
            ->orderBy('player_playlists.priority', 'desc')
            ->first();
    }

    /**
     * Get default playlist for tenant
     */
    private function getDefaultPlaylist(int $tenantId): ?Playlist
    {
        return Playlist::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Activate playlist on player
     */
    private function activatePlaylistOnPlayer(Player $player, Playlist $playlist, ?PlaylistSchedule $schedule = null): void
    {
        DB::transaction(function () use ($player, $playlist, $schedule) {
            // Remove any existing playlist assignments for this player
            $player->playlists()->detach();

            // Attach the new playlist with high priority
            $scheduleConfig = $schedule ? [
                'schedule_id' => $schedule->id,
                'schedule_name' => $schedule->name,
                'auto_assigned' => true,
                'assigned_at' => now()->toDateTimeString(),
            ] : [
                'auto_assigned' => true,
                'assigned_at' => now()->toDateTimeString(),
                'reason' => 'default_playlist',
            ];

            $player->playlists()->attach($playlist->id, [
                'priority' => $schedule ? $schedule->priority : 1,
                'start_date' => now()->toDateString(),
                'end_date' => $schedule && $schedule->end_date ? $schedule->end_date->toDateString() : null,
                'schedule_config' => json_encode($scheduleConfig),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('RecurringPlaylistScheduler job failed permanently: ' . $exception->getMessage(), [
            'tenant_id' => $this->tenantId,
            'force' => $this->force,
            'exception' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $tags = ['playlist-scheduler', 'recurring'];

        if ($this->tenantId) {
            $tags[] = 'tenant:' . $this->tenantId;
        }

        if ($this->force) {
            $tags[] = 'forced';
        }

        return $tags;
    }
}