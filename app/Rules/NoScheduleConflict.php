<?php

namespace App\Rules;

use App\Models\PlaylistSchedule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class NoScheduleConflict implements ValidationRule
{
    private array $scheduleData;
    private ?int $excludeScheduleId;

    public function __construct(array $scheduleData, ?int $excludeScheduleId = null)
    {
        $this->scheduleData = $scheduleData;
        $this->excludeScheduleId = $excludeScheduleId;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $conflicts = $this->findConflicts();

        if ($conflicts->isNotEmpty()) {
            $conflictNames = $conflicts->pluck('name')->implode(', ');
            $fail("Conflito detectado com os agendamentos: {$conflictNames}");
        }
    }

    /**
     * Find conflicting schedules
     */
    private function findConflicts()
    {
        $tenantId = $this->scheduleData['tenant_id'] ?? null;
        $startDate = $this->scheduleData['start_date'] ?? null;
        $endDate = $this->scheduleData['end_date'] ?? null;
        $startTime = $this->scheduleData['start_time'] ?? null;
        $endTime = $this->scheduleData['end_time'] ?? null;
        $daysOfWeek = $this->scheduleData['days_of_week'] ?? null;

        if (!$tenantId) {
            return collect();
        }

        $query = PlaylistSchedule::active()
            ->where('tenant_id', $tenantId);

        if ($this->excludeScheduleId) {
            $query->where('id', '!=', $this->excludeScheduleId);
        }

        $existingSchedules = $query->get();

        // Create a temporary schedule object for conflict checking
        $tempSchedule = new PlaylistSchedule([
            'tenant_id' => $tenantId,
            'start_date' => $startDate ? Carbon::parse($startDate) : null,
            'end_date' => $endDate ? Carbon::parse($endDate) : null,
            'start_time' => $startTime ? Carbon::parse($startTime) : null,
            'end_time' => $endTime ? Carbon::parse($endTime) : null,
            'days_of_week' => $daysOfWeek,
            'is_active' => true,
        ]);

        return $existingSchedules->filter(function (PlaylistSchedule $existingSchedule) use ($tempSchedule) {
            return $tempSchedule->hasConflictWith($existingSchedule);
        });
    }
}