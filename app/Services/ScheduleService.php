<?php

namespace App\Services;

use App\Models\PlaylistSchedule;
use App\Models\Playlist;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScheduleService
{
    public function createSchedule(int $playlistId, array $scheduleData): PlaylistSchedule
    {
        $playlist = Playlist::findOrFail($playlistId);

        $scheduleData['playlist_id'] = $playlistId;
        $scheduleData['tenant_id'] = $playlist->tenant_id;

        // Validate schedule data
        $this->validateScheduleData($scheduleData);

        // Check for conflicts if validation is required
        if (!empty($scheduleData['check_conflicts']) && $scheduleData['check_conflicts']) {
            $conflicts = $this->checkScheduleConflicts($scheduleData);
            if ($conflicts->isNotEmpty()) {
                throw new \InvalidArgumentException(
                    'Conflito detectado com os agendamentos: ' . $conflicts->pluck('name')->implode(', ')
                );
            }
        }

        return PlaylistSchedule::create($scheduleData);
    }

    public function updateSchedule(PlaylistSchedule $schedule, array $scheduleData): PlaylistSchedule
    {
        // Validate schedule data
        $this->validateScheduleData($scheduleData);

        // Check for conflicts excluding current schedule
        if (!empty($scheduleData['check_conflicts']) && $scheduleData['check_conflicts']) {
            $conflicts = $this->checkScheduleConflicts($scheduleData, $schedule->id);
            if ($conflicts->isNotEmpty()) {
                throw new \InvalidArgumentException(
                    'Conflito detectado com os agendamentos: ' . $conflicts->pluck('name')->implode(', ')
                );
            }
        }

        $schedule->update($scheduleData);
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

    protected function validateScheduleData(array $scheduleData): void
    {
        // Validate required fields
        if (empty($scheduleData['name'])) {
            throw new \InvalidArgumentException('Nome do agendamento é obrigatório');
        }

        // Validate date range
        if (!empty($scheduleData['start_date']) && !empty($scheduleData['end_date'])) {
            $startDate = Carbon::parse($scheduleData['start_date']);
            $endDate = Carbon::parse($scheduleData['end_date']);

            if ($startDate->gt($endDate)) {
                throw new \InvalidArgumentException('Data de início deve ser anterior à data de fim');
            }
        }

        // Validate time range
        if (!empty($scheduleData['start_time']) && !empty($scheduleData['end_time'])) {
            $startTime = Carbon::parse($scheduleData['start_time']);
            $endTime = Carbon::parse($scheduleData['end_time']);

            if ($startTime->format('H:i:s') >= $endTime->format('H:i:s')) {
                throw new \InvalidArgumentException('Horário de início deve ser anterior ao horário de fim');
            }
        }

        // Validate days of week
        if (!empty($scheduleData['days_of_week'])) {
            if (!is_array($scheduleData['days_of_week'])) {
                throw new \InvalidArgumentException('Dias da semana devem ser um array');
            }

            foreach ($scheduleData['days_of_week'] as $day) {
                if (!is_int($day) || $day < 0 || $day > 6) {
                    throw new \InvalidArgumentException('Dias da semana devem ser números entre 0 e 6');
                }
            }
        }

        // Validate priority
        if (!empty($scheduleData['priority'])) {
            if (!is_int($scheduleData['priority']) || $scheduleData['priority'] < 1) {
                throw new \InvalidArgumentException('Prioridade deve ser um número inteiro maior que 0');
            }
        }
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
}