<?php

namespace App\Services;

use App\Models\PlaylistSchedule;
use App\Models\Playlist;
use App\Rules\NoScheduleConflict;
use App\Rules\ValidSchedulePriority;
use App\Rules\FutureDateTime;
use App\Rules\ValidScheduleDuration;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Exception;

class PlaylistScheduleValidationService
{
    /**
     * Validate schedule data completely
     */
    public function validateSchedule(array $scheduleData, ?int $excludeScheduleId = null): array
    {
        $validatedData = $this->validateBasicFields($scheduleData, $excludeScheduleId);

        $this->validateBusinessRules($validatedData, $excludeScheduleId);

        return $validatedData;
    }

    /**
     * Validate basic fields with Laravel's validation
     */
    private function validateBasicFields(array $scheduleData, ?int $excludeScheduleId = null): array
    {
        $rules = [
            'playlist_id' => ['required', 'integer', 'exists:playlists,id'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'priority' => ['required', new ValidSchedulePriority()],
            'is_active' => ['boolean'],
        ];

        $messages = [
            'playlist_id.required' => 'Playlist é obrigatória.',
            'playlist_id.exists' => 'Playlist não encontrada.',
            'tenant_id.required' => 'Tenant é obrigatório.',
            'tenant_id.exists' => 'Tenant não encontrado.',
            'name.required' => 'Nome do agendamento é obrigatório.',
            'name.max' => 'Nome do agendamento deve ter no máximo 255 caracteres.',
            'start_date.after_or_equal' => 'Data de início deve ser hoje ou no futuro.',
            'end_date.after_or_equal' => 'Data de fim deve ser posterior ou igual à data de início.',
            'start_time.date_format' => 'Horário de início deve estar no formato HH:MM.',
            'end_time.date_format' => 'Horário de fim deve estar no formato HH:MM.',
            'end_time.after' => 'Horário de fim deve ser posterior ao horário de início.',
            'days_of_week.array' => 'Dias da semana deve ser uma lista.',
            'days_of_week.*.integer' => 'Dia da semana deve ser um número.',
            'days_of_week.*.between' => 'Dia da semana deve estar entre 0 (domingo) e 6 (sábado).',
            'priority.required' => 'Prioridade é obrigatória.',
            'is_active.boolean' => 'Status ativo deve ser verdadeiro ou falso.',
        ];

        $validator = Validator::make($scheduleData, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validate business rules
     */
    private function validateBusinessRules(array $scheduleData, ?int $excludeScheduleId = null): void
    {
        // Validate duration
        if ($scheduleData['start_time'] && $scheduleData['end_time']) {
            $durationValidator = Validator::make($scheduleData, [
                'start_time' => [new ValidScheduleDuration($scheduleData)],
            ]);

            if ($durationValidator->fails()) {
                throw new ValidationException($durationValidator);
            }
        }

        // Validate conflicts
        $conflictValidator = Validator::make($scheduleData, [
            'name' => [new NoScheduleConflict($scheduleData, $excludeScheduleId)],
        ]);

        if ($conflictValidator->fails()) {
            throw new ValidationException($conflictValidator);
        }

        // Additional business validations
        $this->validatePlaylistAccess($scheduleData);
        $this->validateScheduleLogic($scheduleData);
    }

    /**
     * Validate playlist access for tenant
     */
    private function validatePlaylistAccess(array $scheduleData): void
    {
        $playlist = Playlist::find($scheduleData['playlist_id']);

        if (!$playlist || $playlist->tenant_id !== $scheduleData['tenant_id']) {
            throw new Exception('Playlist não pertence ao tenant especificado.');
        }
    }

    /**
     * Validate schedule logic
     */
    private function validateScheduleLogic(array $scheduleData): void
    {
        // If days_of_week is specified, validate that start_date and end_date span the correct period
        if (!empty($scheduleData['days_of_week']) && $scheduleData['start_date'] && $scheduleData['end_date']) {
            $startDate = Carbon::parse($scheduleData['start_date']);
            $endDate = Carbon::parse($scheduleData['end_date']);

            // Check if the date range includes at least one of the specified days
            $foundDay = false;
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                if (in_array($currentDate->dayOfWeek, $scheduleData['days_of_week'])) {
                    $foundDay = true;
                    break;
                }
                $currentDate->addDay();
            }

            if (!$foundDay) {
                throw new Exception('O período selecionado não inclui nenhum dos dias da semana especificados.');
            }
        }

        // Validate time logic for same-day schedules
        if ($scheduleData['start_time'] && $scheduleData['end_time']) {
            // Parse times and ensure they're on the same date
            $baseDate = Carbon::today();
            $startTime = $baseDate->copy()->setTimeFromTimeString($scheduleData['start_time']);
            $endTime = $baseDate->copy()->setTimeFromTimeString($scheduleData['end_time']);

            // Handle overnight schedules (end time is next day)
            if ($endTime->lt($startTime)) {
                $endTime->addDay();
            }

            // Ensure minimum gap between schedules (prevent immediate consecutive schedules)
            $duration = $startTime->diffInMinutes($endTime);

            if ($duration < 5) {
                throw new Exception('A duração mínima do agendamento é de 5 minutos.');
            }

            if ($duration > 1440) { // 24 hours
                throw new Exception('A duração máxima do agendamento é de 24 horas.');
            }
        }
    }

    /**
     * Check if a schedule can override existing schedules based on priority
     */
    public function canOverrideExistingSchedules(array $scheduleData, ?int $excludeScheduleId = null): array
    {
        $conflicts = $this->findConflictingSchedules($scheduleData, $excludeScheduleId);
        $canOverride = [];
        $blockedBy = [];

        foreach ($conflicts as $conflict) {
            if ($scheduleData['priority'] > $conflict->priority) {
                $canOverride[] = [
                    'id' => $conflict->id,
                    'name' => $conflict->name,
                    'priority' => $conflict->priority,
                    'can_override' => true,
                ];
            } else {
                $blockedBy[] = [
                    'id' => $conflict->id,
                    'name' => $conflict->name,
                    'priority' => $conflict->priority,
                    'can_override' => false,
                ];
            }
        }

        return [
            'can_override' => $canOverride,
            'blocked_by' => $blockedBy,
            'has_conflicts' => !empty($blockedBy),
        ];
    }

    /**
     * Find conflicting schedules
     */
    private function findConflictingSchedules(array $scheduleData, ?int $excludeScheduleId = null)
    {
        $tenantId = $scheduleData['tenant_id'];

        $query = PlaylistSchedule::active()
            ->where('tenant_id', $tenantId);

        if ($excludeScheduleId) {
            $query->where('id', '!=', $excludeScheduleId);
        }

        $existingSchedules = $query->get();

        // Create a temporary schedule object for conflict checking
        $tempSchedule = new PlaylistSchedule([
            'tenant_id' => $tenantId,
            'start_date' => $scheduleData['start_date'] ? Carbon::parse($scheduleData['start_date']) : null,
            'end_date' => $scheduleData['end_date'] ? Carbon::parse($scheduleData['end_date']) : null,
            'start_time' => $scheduleData['start_time'] ? Carbon::parse($scheduleData['start_time']) : null,
            'end_time' => $scheduleData['end_time'] ? Carbon::parse($scheduleData['end_time']) : null,
            'days_of_week' => $scheduleData['days_of_week'] ?? null,
            'is_active' => true,
        ]);

        return $existingSchedules->filter(function (PlaylistSchedule $existingSchedule) use ($tempSchedule) {
            return $tempSchedule->hasConflictWith($existingSchedule);
        });
    }

    /**
     * Get detailed validation errors for API responses
     */
    public function getValidationErrors(Exception $exception): array
    {
        if ($exception instanceof ValidationException) {
            return [
                'type' => 'validation',
                'errors' => $exception->errors(),
                'message' => 'Dados de agendamento inválidos.',
            ];
        }

        return [
            'type' => 'business_rule',
            'errors' => ['general' => [$exception->getMessage()]],
            'message' => 'Regra de negócio violada.',
        ];
    }

    /**
     * Validate schedule update
     */
    public function validateScheduleUpdate(PlaylistSchedule $schedule, array $updateData): array
    {
        // Merge existing data with update data
        $scheduleData = array_merge([
            'playlist_id' => $schedule->playlist_id,
            'tenant_id' => $schedule->tenant_id,
            'name' => $schedule->name,
            'start_date' => $schedule->start_date?->toDateString(),
            'end_date' => $schedule->end_date?->toDateString(),
            'start_time' => $schedule->start_time?->format('H:i'),
            'end_time' => $schedule->end_time?->format('H:i'),
            'days_of_week' => $schedule->days_of_week,
            'priority' => $schedule->priority,
            'is_active' => $schedule->is_active,
        ], $updateData);

        return $this->validateSchedule($scheduleData, $schedule->id);
    }
}