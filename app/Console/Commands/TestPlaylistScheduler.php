<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Player;
use App\Models\Playlist;
use App\Models\PlaylistSchedule;
use App\Jobs\RecurringPlaylistScheduler;
use App\Services\ScheduleService;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Exception;

class TestPlaylistScheduler extends Command
{
    protected $signature = 'test:playlist-scheduler
                          {--tenant= : Test with specific tenant ID}
                          {--force : Force execution ignoring time checks}';

    protected $description = 'Test playlist scheduler functionality';

    public function handle()
    {
        $this->info('🎵 Testing Playlist Scheduler...');

        try {
            $tenantId = $this->option('tenant');
            $force = $this->option('force');

            // Test 1: Check if required data exists
            $this->info('📋 Test 1: Checking required data...');
            $this->checkRequiredData($tenantId);

            // Test 2: Test ScheduleService
            $this->info('📋 Test 2: Testing ScheduleService...');
            $this->testScheduleService($tenantId);

            // Test 3: Run the scheduler job manually
            $this->info('📋 Test 3: Testing RecurringPlaylistScheduler job...');
            $this->testSchedulerJob($tenantId, $force);

            // Test 4: Check results
            $this->info('📋 Test 4: Checking results...');
            $this->checkResults($tenantId);

            $this->info('🎉 All tests completed successfully!');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->error("Stack trace: {$e->getTraceAsString()}");
            return Command::FAILURE;
        }
    }

    private function checkRequiredData(?string $tenantId): void
    {
        $tenant = $tenantId ? Tenant::find($tenantId) : Tenant::first();

        if (!$tenant) {
            throw new Exception('No tenant found. Please create a tenant first.');
        }

        $this->info("✅ Tenant found: {$tenant->name} (ID: {$tenant->id})");

        $players = Player::where('tenant_id', $tenant->id)->get();
        if ($players->isEmpty()) {
            $this->warn('⚠️ No players found for this tenant');
        } else {
            $this->info("✅ Found {$players->count()} players");
        }

        $playlists = Playlist::where('tenant_id', $tenant->id)->get();
        if ($playlists->isEmpty()) {
            $this->warn('⚠️ No playlists found for this tenant');
        } else {
            $this->info("✅ Found {$playlists->count()} playlists");

            $defaultPlaylist = $playlists->where('is_default', true)->first();
            if ($defaultPlaylist) {
                $this->info("✅ Default playlist: {$defaultPlaylist->name}");
            } else {
                $this->warn('⚠️ No default playlist found');
            }
        }

        $schedules = PlaylistSchedule::where('tenant_id', $tenant->id)->get();
        if ($schedules->isEmpty()) {
            $this->warn('⚠️ No playlist schedules found for this tenant');
        } else {
            $this->info("✅ Found {$schedules->count()} playlist schedules");

            $activeSchedules = $schedules->where('is_active', true);
            $this->info("✅ Active schedules: {$activeSchedules->count()}");
        }
    }

    private function testScheduleService(?string $tenantId): void
    {
        $scheduleService = app(ScheduleService::class);
        $currentDateTime = now();

        $tenant = $tenantId ? Tenant::find($tenantId) : Tenant::first();

        $activeSchedules = $scheduleService->getActiveSchedulesForDateTime(
            $currentDateTime,
            $tenant->id
        );

        $this->info("✅ Found {$activeSchedules->count()} active schedules for current time");

        if ($activeSchedules->isNotEmpty()) {
            $highestPriority = $activeSchedules->first();
            $this->info("✅ Highest priority schedule: {$highestPriority->name} (Priority: {$highestPriority->priority})");
        }

        // Test with different times
        $testTimes = [
            Carbon::parse('09:00:00'),
            Carbon::parse('12:00:00'),
            Carbon::parse('18:00:00'),
            Carbon::parse('22:00:00'),
        ];

        foreach ($testTimes as $testTime) {
            $testDateTime = now()->setTimeFromTimeString($testTime->format('H:i:s'));
            $testSchedules = $scheduleService->getActiveSchedulesForDateTime($testDateTime, $tenant->id);
            $this->info("✅ At {$testTime->format('H:i')}: {$testSchedules->count()} active schedules");
        }
    }

    private function testSchedulerJob(?string $tenantId, bool $force): void
    {
        $this->info('🚀 Running RecurringPlaylistScheduler job...');

        $job = new RecurringPlaylistScheduler(
            tenantId: $tenantId ? (int) $tenantId : null,
            force: $force
        );

        // Execute the job
        $scheduleService = app(ScheduleService::class);
        $job->handle($scheduleService);

        $this->info('✅ Job executed successfully');
    }

    private function checkResults(?string $tenantId): void
    {
        $tenant = $tenantId ? Tenant::find($tenantId) : Tenant::first();

        $players = Player::where('tenant_id', $tenant->id)
            ->with(['playlists' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('player_playlists.end_date')
                      ->orWhere('player_playlists.end_date', '>=', now()->toDateString());
                })
                ->where(function ($q) {
                    $q->whereNull('player_playlists.start_date')
                      ->orWhere('player_playlists.start_date', '<=', now()->toDateString());
                })
                ->orderBy('player_playlists.priority', 'desc');
            }])
            ->get();

        foreach ($players as $player) {
            $activePlaylist = $player->playlists->first();

            if ($activePlaylist) {
                $scheduleConfig = is_string($activePlaylist->pivot->schedule_config)
                    ? json_decode($activePlaylist->pivot->schedule_config, true)
                    : ($activePlaylist->pivot->schedule_config ?? []);
                $autoAssigned = $scheduleConfig['auto_assigned'] ?? false;

                $this->info("✅ Player: {$player->name}");
                $this->info("   - Active playlist: {$activePlaylist->name}");
                $this->info("   - Priority: {$activePlaylist->pivot->priority}");
                $this->info("   - Auto-assigned: " . ($autoAssigned ? 'Yes' : 'No'));

                if (isset($scheduleConfig['schedule_name'])) {
                    $this->info("   - From schedule: {$scheduleConfig['schedule_name']}");
                }

                if (isset($scheduleConfig['assigned_at'])) {
                    $this->info("   - Assigned at: {$scheduleConfig['assigned_at']}");
                }
            } else {
                $this->warn("⚠️ Player {$player->name} has no active playlist");
            }
        }
    }
}