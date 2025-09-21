<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class ValidScheduleDuration implements ValidationRule
{
    private array $data;
    private string $startField;
    private string $endField;

    public function __construct(array $data, string $startField = 'start_time', string $endField = 'end_time')
    {
        $this->data = $data;
        $this->startField = $startField;
        $this->endField = $endField;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $startTime = $this->data[$this->startField] ?? null;
        $endTime = $this->data[$this->endField] ?? null;

        // If either start or end time is missing, skip validation
        if (!$startTime || !$endTime) {
            return;
        }

        try {
            // Parse times and ensure they're on the same date
            $baseDate = Carbon::today();
            $start = $baseDate->copy()->setTimeFromTimeString($startTime);
            $end = $baseDate->copy()->setTimeFromTimeString($endTime);
        } catch (\Exception $e) {
            return; // Invalid date format, but that's not this rule's responsibility
        }

        // Handle overnight schedules (end time is next day)
        if ($end->lt($start)) {
            $end->addDay();
        }

        $durationInMinutes = $start->diffInMinutes($end);

        // Minimum duration: 5 minutes
        if ($durationInMinutes < 5) {
            $fail('A duração mínima do agendamento é de 5 minutos.');
            return;
        }

        // Maximum duration: 24 hours (1440 minutes)
        if ($durationInMinutes > 1440) {
            $fail('A duração máxima do agendamento é de 24 horas.');
            return;
        }
    }
}