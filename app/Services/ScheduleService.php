<?php

namespace App\Services;

use App\Models\PlaylistSchedule;
use App\Models\Playlist;
use App\Services\PlaylistScheduleValidationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScheduleService
{
    private PlaylistScheduleValidationService $validationService;

    public function __construct(PlaylistScheduleValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    public function createSchedule(int $playlistId, array $scheduleData): PlaylistSchedule
    {
        $playlist = Playlist::findOrFail($playlistId);

        $scheduleData['playlist_id'] = $playlistId;
        $scheduleData['tenant_id'] = $playlist->tenant_id;

        // Use the new validation service
        $validatedData = $this->validationService->validateSchedule($scheduleData);

        return PlaylistSchedule::create($validatedData);
    }

    public function updateSchedule(PlaylistSchedule $schedule, array $scheduleData): PlaylistSchedule
    {
        // Use the new validation service for updates
        $validatedData = $this->validationService->validateScheduleUpdate($schedule, $scheduleData);

        $schedule->update($validatedData);
        return $schedule->fresh();
    }

    public function getActiveSchedulesForDateTime(Carbon $dateTime, ?int $tenantId = null): Collection
    {
        $query = PlaylistSchedule::currentlyActive($dateTime)
            ->with(['playlist', 'tenant'])
            ->byPriority('desc');

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        return $query->get();
    }

    public function getHighestPriorityScheduleForDateTime(Carbon $dateTime, int $tenantId): ?PlaylistSchedule
    {
        return $this->getActiveSchedulesForDateTime($dateTime, $tenantId)->first();
    }

    public function checkScheduleConflicts(array $scheduleData, ?int $excludeScheduleId = null): Collection
    {
        $query = PlaylistSchedule::active()
            ->where('tenant_id', $scheduleData['tenant_id']);

        if ($excludeScheduleId) {
            $query->where('id', '!=', $excludeScheduleId);
        }

        $existingSchedules = $query->get();

        // Create a temporary schedule object for conflict checking
        $tempSchedule = new PlaylistSchedule($scheduleData);

        return $existingSchedules->filter(function (PlaylistSchedule $existingSchedule) use ($tempSchedule) {
            return $tempSchedule->hasConflictWith($existingSchedule);
        });
    }

    public function getSchedulesByPlaylist(int $playlistId): Collection
    {
        return PlaylistSchedule::where('playlist_id', $playlistId)
            ->with(['playlist', 'tenant'])
            ->orderBy('priority', 'desc')
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getSchedulesByTenant(int $tenantId, bool $activeOnly = false): Collection
    {
        $query = PlaylistSchedule::forTenant($tenantId)
            ->with(['playlist']);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('priority', 'desc')
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();
    }

    public function duplicateSchedule(PlaylistSchedule $schedule, array $overrides = []): PlaylistSchedule
    {
        $scheduleData = $schedule->toArray();

        // Remove ID and timestamps
        unset($scheduleData['id'], $scheduleData['created_at'], $scheduleData['updated_at']);

        // Apply overrides
        $scheduleData = array_merge($scheduleData, $overrides);

        // Add suffix to name if not overridden
        if (!isset($overrides['name'])) {
            $scheduleData['name'] = $schedule->name . ' (Cópia)';
        }

        return $this->createSchedule($schedule->playlist_id, $scheduleData);
    }

    public function getSchedulePreview(array $scheduleData, int $days = 7): array
    {
        $preview = [];
        $startDate = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $daySchedules = [];

            // Check if this schedule would be active on this date
            if ($this->wouldBeActiveOnDate($scheduleData, $date)) {
                $daySchedules[] = [
                    'name' => $scheduleData['name'] ?? 'Novo Agendamento',
                    'time_range' => $this->formatTimeRange($scheduleData),
                    'priority' => $scheduleData['priority'] ?? 1,
                ];
            }

            $preview[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->locale('pt_BR')->isoFormat('dddd'),
                'schedules' => $daySchedules,
            ];
        }

        return $preview;
    }


    protected function wouldBeActiveOnDate(array $scheduleData, Carbon $date): bool
    {
        // Check date range
        if (!empty($scheduleData['start_date'])) {
            $startDate = Carbon::parse($scheduleData['start_date']);
            if ($date->lt($startDate)) {
                return false;
            }
        }

        if (!empty($scheduleData['end_date'])) {
            $endDate = Carbon::parse($scheduleData['end_date']);
            if ($date->gt($endDate)) {
                return false;
            }
        }

        // Check day of week
        if (!empty($scheduleData['days_of_week'])) {
            if (!in_array($date->dayOfWeek, $scheduleData['days_of_week'])) {
                return false;
            }
        }

        return true;
    }

    protected function formatTimeRange(array $scheduleData): string
    {
        $startTime = !empty($scheduleData['start_time'])
            ? Carbon::parse($scheduleData['start_time'])->format('H:i')
            : 'Início do dia';

        $endTime = !empty($scheduleData['end_time'])
            ? Carbon::parse($scheduleData['end_time'])->format('H:i')
            : 'Fim do dia';

        if (empty($scheduleData['start_time']) && empty($scheduleData['end_time'])) {
            return 'Todo o dia';
        }

        return "{$startTime} - {$endTime}";
    }

    /**
     * Create schedule with conflict override capability
     */
    public function createScheduleWithOverride(int $playlistId, array $scheduleData, bool $allowOverride = false): PlaylistSchedule
    {
        $playlist = Playlist::findOrFail($playlistId);

        $scheduleData['playlist_id'] = $playlistId;
        $scheduleData['tenant_id'] = $playlist->tenant_id;

        if ($allowOverride) {
            // Check what conflicts can be overridden
            $overrideAnalysis = $this->validationService->canOverrideExistingSchedules($scheduleData);

            if ($overrideAnalysis['has_conflicts']) {
                throw new \InvalidArgumentException(
                    'Cannot override schedules with higher or equal priority: ' .
                    collect($overrideAnalysis['blocked_by'])->pluck('name')->implode(', ')
                );
            }

            // Deactivate schedules that can be overridden
            if (!empty($overrideAnalysis['can_override'])) {
                $overrideIds = collect($overrideAnalysis['can_override'])->pluck('id');
                PlaylistSchedule::whereIn('id', $overrideIds)->update(['is_active' => false]);
            }
        }

        // Validate and create
        $validatedData = $this->validationService->validateSchedule($scheduleData);
        return PlaylistSchedule::create($validatedData);
    }

    /**
     * Get validation service instance
     */
    public function getValidationService(): PlaylistScheduleValidationService
    {
        return $this->validationService;
    }

    /**
     * Check schedule conflicts without creating
     */
    public function checkConflicts(array $scheduleData, ?int $excludeScheduleId = null): array
    {
        return $this->validationService->canOverrideExistingSchedules($scheduleData, $excludeScheduleId);
    }

    /**
     * Validate schedule data without creating
     */
    public function validateScheduleData(array $scheduleData, ?int $excludeScheduleId = null): array
    {
        return $this->validationService->validateSchedule($scheduleData, $excludeScheduleId);
    }
}