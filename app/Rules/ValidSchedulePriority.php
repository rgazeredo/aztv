<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSchedulePriority implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Priority must be between 1 and 10
        if (!is_numeric($value)) {
            $fail('A prioridade deve ser um número.');
            return;
        }

        $priority = (int) $value;

        if ($priority < 1 || $priority > 10) {
            $fail('A prioridade deve estar entre 1 e 10.');
            return;
        }
    }
}