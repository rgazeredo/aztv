<?php

namespace App\Jobs;

use App\Models\PlaylistSchedule;
use App\Models\Tenant;
use App\Models\Player;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPlaylists implements ShouldQueue
{
    use Queueable;

    protected ScheduleService $scheduleService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->scheduleService = app(ScheduleService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $currentDateTime = now();

        Log::info('ProcessScheduledPlaylists job started', [
            'datetime' => $currentDateTime->toDateTimeString()
        ]);

        // Process schedules for each tenant
        $tenants = Tenant::active()->get();

        foreach ($tenants as $tenant) {
            $this->processTenantSchedules($tenant, $currentDateTime);
        }

        Log::info('ProcessScheduledPlaylists job completed');
    }

    protected function processTenantSchedules(Tenant $tenant, Carbon $dateTime): void
    {
        try {
            // Get the highest priority active schedule for this tenant at current time
            $activeSchedule = $this->scheduleService->getHighestPriorityScheduleForDateTime(
                $dateTime,
                $tenant->id
            );

            // Get all players for this tenant
            $players = Player::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->get();

            if ($activeSchedule) {
                $this->applyScheduleToPlayers($activeSchedule, $players, $dateTime);
            } else {
                $this->applyDefaultPlaylistToPlayers($tenant, $players, $dateTime);
            }

        } catch (\Exception $e) {
            Log::error('Error processing tenant schedules', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function applyScheduleToPlayers(
        PlaylistSchedule $schedule,
        $players,
        Carbon $dateTime
    ): void {
        Log::info('Applying scheduled playlist to players', [
            'schedule_id' => $schedule->id,
            'schedule_name' => $schedule->name,
            'playlist_id' => $schedule->playlist_id,
            'tenant_id' => $schedule->tenant_id,
            'player_count' => $players->count(),
            'datetime' => $dateTime->toDateTimeString()
        ]);

        foreach ($players as $player) {
            try {
                // Check if player already has this playlist as current
                $currentPlaylist = $this->getCurrentPlayerPlaylist($player);

                if (!$currentPlaylist || $currentPlaylist->id !== $schedule->playlist_id) {
                    $this->assignPlaylistToPlayer($player, $schedule->playlist, $schedule);

                    Log::info('Playlist assigned to player via schedule', [
                        'player_id' => $player->id,
                        'player_name' => $player->name,
                        'playlist_id' => $schedule->playlist_id,
                        'playlist_name' => $schedule->playlist->name,
                        'schedule_id' => $schedule->id,
                        'schedule_name' => $schedule->name
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Error applying schedule to player', [
                    'player_id' => $player->id,
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function applyDefaultPlaylistToPlayers(
        Tenant $tenant,
        $players,
        Carbon $dateTime
    ): void {
        // Find default playlist for tenant
        $defaultPlaylist = $tenant->playlists()
            ->where('is_default', true)
            ->first();

        if (!$defaultPlaylist) {
            Log::debug('No default playlist found for tenant', [
                'tenant_id' => $tenant->id
            ]);
            return;
        }

        Log::info('Applying default playlist to players (no active schedules)', [
            'tenant_id' => $tenant->id,
            'playlist_id' => $defaultPlaylist->id,
            'playlist_name' => $defaultPlaylist->name,
            'player_count' => $players->count(),
            'datetime' => $dateTime->toDateTimeString()
        ]);

        foreach ($players as $player) {
            try {
                $currentPlaylist = $this->getCurrentPlayerPlaylist($player);

                if (!$currentPlaylist || $currentPlaylist->id !== $defaultPlaylist->id) {
                    $this->assignPlaylistToPlayer($player, $defaultPlaylist);

                    Log::info('Default playlist assigned to player', [
                        'player_id' => $player->id,
                        'player_name' => $player->name,
                        'playlist_id' => $defaultPlaylist->id,
                        'playlist_name' => $defaultPlaylist->name
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Error applying default playlist to player', [
                    'player_id' => $player->id,
                    'default_playlist_id' => $defaultPlaylist->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function getCurrentPlayerPlaylist(Player $player)
    {
        // Get the current active playlist for the player
        // This assumes there's a relationship or pivot table to track current playlists
        return $player->playlists()
            ->wherePivot('is_current', true)
            ->first();
    }

    protected function assignPlaylistToPlayer(
        Player $player,
        $playlist,
        ?PlaylistSchedule $schedule = null
    ): void {
        // First, remove any current playlist assignments
        $player->playlists()->updateExistingPivot(
            $player->playlists()->pluck('playlists.id'),
            ['is_current' => false]
        );

        // Assign the new playlist as current
        $pivotData = [
            'is_current' => true,
            'assigned_at' => now(),
            'assigned_via_schedule' => $schedule ? $schedule->id : null,
        ];

        if (!$player->playlists()->where('playlists.id', $playlist->id)->exists()) {
            // If playlist is not attached to player, attach it
            $player->playlists()->attach($playlist->id, $pivotData);
        } else {
            // If already attached, just update the pivot
            $player->playlists()->updateExistingPivot($playlist->id, $pivotData);
        }

        // Update player's last_playlist_update timestamp
        $player->update([
            'last_playlist_update' => now()
        ]);
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessScheduledPlaylists job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
