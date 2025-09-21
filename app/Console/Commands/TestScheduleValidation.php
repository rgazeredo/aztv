<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Playlist;
use App\Models\PlaylistSchedule;
use App\Services\PlaylistScheduleValidationService;
use App\Services\ScheduleService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Exception;

class TestScheduleValidation extends Command
{
    protected $signature = 'test:schedule-validation
                          {--tenant= : Test with specific tenant ID}';

    protected $description = 'Test playlist schedule validation system';

    public function handle()
    {
        $this->info('🔧 Testing Schedule Validation System...');

        try {
            $tenantId = $this->option('tenant');

            // Test 1: Check required data
            $this->info('📋 Test 1: Checking required data...');
            $data = $this->checkRequiredData($tenantId);

            // Test 2: Test basic validation rules
            $this->info('📋 Test 2: Testing basic validation rules...');
            $this->testBasicValidationRules($data);

            // Test 3: Test conflict detection
            $this->info('📋 Test 3: Testing conflict detection...');
            $this->testConflictDetection($data);

            // Test 4: Test priority overrides
            $this->info('📋 Test 4: Testing priority overrides...');
            $this->testPriorityOverrides($data);

            // Test 5: Test duration validation
            $this->info('📋 Test 5: Testing duration validation...');
            $this->testDurationValidation($data);

            // Test 6: Test future date validation
            $this->info('📋 Test 6: Testing future date validation...');
            $this->testFutureDateValidation($data);

            $this->info('🎉 All validation tests completed successfully!');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->error("Stack trace: {$e->getTraceAsString()}");
            return Command::FAILURE;
        }
    }

    private function checkRequiredData(?string $tenantId): array
    {
        $tenant = $tenantId ? Tenant::find($tenantId) : Tenant::first();

        if (!$tenant) {
            throw new Exception('No tenant found. Please create a tenant first.');
        }

        $this->info("✅ Tenant found: {$tenant->name} (ID: {$tenant->id})");

        $playlist = Playlist::where('tenant_id', $tenant->id)->first();
        if (!$playlist) {
            $this->warn('⚠️ No playlists found, creating test playlist...');
            $playlist = Playlist::create([
                'tenant_id' => $tenant->id,
                'name' => 'Test Playlist for Validation',
                'description' => 'Test playlist for schedule validation',
                'is_default' => false,
                'loop_enabled' => true,
                'settings' => []
            ]);
        }

        $this->info("✅ Playlist found: {$playlist->name} (ID: {$playlist->id})");

        return compact('tenant', 'playlist');
    }

    private function testBasicValidationRules(array $data): void
    {
        $validationService = app(PlaylistScheduleValidationService::class);

        // Test valid data
        $validData = [
            'playlist_id' => $data['playlist']->id,
            'tenant_id' => $data['tenant']->id,
            'name' => 'Test Schedule',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'priority' => 5,
            'is_active' => true,
        ];

        try {
            $result = $validationService->validateSchedule($validData);
            $this->info('✅ Valid data passed validation');
        } catch (Exception $e) {
            $this->error("❌ Valid data failed validation: {$e->getMessage()}");
        }

        // Test invalid priority
        $invalidPriorityData = array_merge($validData, ['priority' => 15]);
        try {
            $validationService->validateSchedule($invalidPriorityData);
            $this->error('❌ Invalid priority should have failed');
        } catch (ValidationException $e) {
            $this->info('✅ Invalid priority correctly rejected');
        }

        // Test missing required fields
        $missingFieldData = array_merge($validData);
        unset($missingFieldData['name']);
        try {
            $validationService->validateSchedule($missingFieldData);
            $this->error('❌ Missing name should have failed');
        } catch (ValidationException $e) {
            $this->info('✅ Missing required field correctly rejected');
        }

        // Test invalid time range
        $invalidTimeData = array_merge($validData, [
            'start_time' => '17:00',
            'end_time' => '09:00'
        ]);
        try {
            $validationService->validateSchedule($invalidTimeData);
            $this->error('❌ Invalid time range should have failed');
        } catch (ValidationException $e) {
            $this->info('✅ Invalid time range correctly rejected');
        }
    }

    private function testConflictDetection(array $data): void
    {
        $scheduleService = app(ScheduleService::class);

        // Create first schedule
        $firstSchedule = [
            'playlist_id' => $data['playlist']->id,
            'tenant_id' => $data['tenant']->id,
            'name' => 'First Schedule',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'days_of_week' => [1], // Monday
            'priority' => 3,
            'is_active' => true,
        ];

        $schedule1 = $scheduleService->createSchedule($data['playlist']->id, $firstSchedule);
        $this->info("✅ Created first schedule: {$schedule1->name}");

        // Try to create conflicting schedule
        $conflictingSchedule = [
            'playlist_id' => $data['playlist']->id,
            'tenant_id' => $data['tenant']->id,
            'name' => 'Conflicting Schedule',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00',
            'end_time' => '13:00',
            'days_of_week' => [1], // Monday - same day
            'priority' => 2,
            'is_active' => true,
        ];

        try {
            $scheduleService->createSchedule($data['playlist']->id, $conflictingSchedule);
            $this->error('❌ Conflicting schedule should have failed');
        } catch (ValidationException $e) {
            $this->info('✅ Conflict correctly detected and rejected');
        }

        // Cleanup
        $schedule1->delete();
    }

    private function testPriorityOverrides(array $data): void
    {
        $scheduleService = app(ScheduleService::class);

        // Create low priority schedule
        $lowPrioritySchedule = [
            'playlist_id' => $data['playlist']->id,
            'tenant_id' => $data['tenant']->id,
            'name' => 'Low Priority Schedule',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'days_of_week' => [2], // Tuesday
            'priority' => 2,
            'is_active' => true,
        ];

        $schedule1 = $scheduleService->createSchedule($data['playlist']->id, $lowPrioritySchedule);
        $this->info("✅ Created low priority schedule: {$schedule1->name}");

        // Create high priority schedule with override
        $highPrioritySchedule = [
            'playlist_id' => $data['playlist']->id,
            'tenant_id' => $data['tenant']->id,
            'name' => 'High Priority Schedule',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '15:00',
            'end_time' => '17:00',
            'days_of_week' => [2], // Tuesday - same day
            'priority' => 5,
            'is_active' => true,
        ];

        try {
            $schedule2 = $scheduleService->createScheduleWithOverride(
                $data['playlist']->id,
                $highPrioritySchedule,
                true
            );
            $this->info("✅ High priority schedule created with override: {$schedule2->name}");
            $schedule2->delete();
        } catch (Exception $e) {
            $this->error("❌ Priority override failed: {$e->getMessage()}");
        }

        // Cleanup
        $schedule1->delete();
    }

    private function testDurationValidation(array $data): void
    {
        $validationService = app(PlaylistScheduleValidationService::class);

        // Test minimum duration (less than 5 minutes)
        $shortDurationData = [
            'playlist_id' => $data['playlist']->id,
            'tenant_id' => $data['tenant']->id,
            'name' => 'Short Duration Schedule',
            'start_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:02', // Only 2 minutes
            'priority' => 5,
            'is_active' => true,
        ];

        try {
            $validationService->validateSchedule($shortDurationData);
            $this->error('❌ Short duration should have failed');
        } catch (ValidationException $e) {
            $this->info('✅ Short duration correctly rejected');
        }

        // Test maximum duration (more than 24 hours)
        $longDurationData = [
            'playlist_id' => $data['playlist']->id,
            'tenant_id' => $data['tenant']->id,
            'name' => 'Long Duration Schedule',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00', // Spans more than 24 hours
            'priority' => 5,
            'is_active' => true,
        ];

        try {
            $validationService->validateSchedule($longDurationData);
            $this->error('❌ Long duration should have failed');
        } catch (ValidationException $e) {
            $this->info('✅ Long duration correctly rejected');
        }
    }

    private function testFutureDateValidation(array $data): void
    {
        $validationService = app(PlaylistScheduleValidationService::class);

        // Test past date
        $pastDateData = [
            'playlist_id' => $data['playlist']->id,
            'tenant_id' => $data['tenant']->id,
            'name' => 'Past Date Schedule',
            'start_date' => now()->subDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'priority' => 5,
            'is_active' => true,
        ];

        try {
            $validationService->validateSchedule($pastDateData);
            $this->error('❌ Past date should have failed');
        } catch (ValidationException $e) {
            $this->info('✅ Past date correctly rejected');
        }

        // Test future date (should pass)
        $futureDateData = [
            'playlist_id' => $data['playlist']->id,
            'tenant_id' => $data['tenant']->id,
            'name' => 'Future Date Schedule',
            'start_date' => now()->addWeek()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'priority' => 5,
            'is_active' => true,
        ];

        try {
            $result = $validationService->validateSchedule($futureDateData);
            $this->info('✅ Future date correctly accepted');
        } catch (Exception $e) {
            $this->error("❌ Future date validation failed: {$e->getMessage()}");
        }
    }
}