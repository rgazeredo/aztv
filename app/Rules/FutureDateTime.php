<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class FutureDateTime implements ValidationRule
{
    private bool $allowNull;
    private ?string $comparisonField;
    private array $data;

    public function __construct(bool $allowNull = false, ?string $comparisonField = null, array $data = [])
    {
        $this->allowNull = $allowNull;
        $this->comparisonField = $comparisonField;
        $this->data = $data;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Allow null values if specified
        if ($value === null && $this->allowNull) {
            return;
        }

        if ($value === null) {
            $fail('Este campo é obrigatório.');
            return;
        }

        try {
            $dateTime = Carbon::parse($value);
        } catch (\Exception $e) {
            $fail('Data/hora inválida.');
            return;
        }

        // Check if date is in the future (with 1 minute tolerance for current operations)
        if ($dateTime->lte(now()->addMinute())) {
            $fail('A data/hora deve ser no futuro.');
            return;
        }

        // If there's a comparison field, validate relationship
        if ($this->comparisonField && isset($this->data[$this->comparisonField])) {
            try {
                $comparisonDateTime = Carbon::parse($this->data[$this->comparisonField]);

                // For end_time, it should be after start_time
                if (str_contains($attribute, 'end') && $dateTime->lte($comparisonDateTime)) {
                    $fail('A data/hora de fim deve ser posterior à data/hora de início.');
                    return;
                }

                // For start_time, it should be before end_time
                if (str_contains($attribute, 'start') && $dateTime->gte($comparisonDateTime)) {
                    $fail('A data/hora de início deve ser anterior à data/hora de fim.');
                    return;
                }
            } catch (\Exception $e) {
                // Comparison field is invalid, but that's not this rule's responsibility
            }
        }
    }
}