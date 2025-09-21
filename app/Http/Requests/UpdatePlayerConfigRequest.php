<?php

namespace App\Http\Requests;

use App\Models\PlayerSettings;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayerConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('player'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $availableSettings = PlayerSettings::getAvailableSettings();
        $rules = [
            'settings' => 'required|array',
        ];

        // Dynamic validation based on available settings
        foreach ($availableSettings as $key => $config) {
            $fieldRules = ['sometimes'];

            // Type validation
            switch ($config['type']) {
                case 'integer':
                    $fieldRules[] = 'integer';
                    if (isset($config['min'])) {
                        $fieldRules[] = 'min:' . $config['min'];
                    }
                    if (isset($config['max'])) {
                        $fieldRules[] = 'max:' . $config['max'];
                    }
                    break;

                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;

                case 'float':
                    $fieldRules[] = 'numeric';
                    if (isset($config['min'])) {
                        $fieldRules[] = 'min:' . $config['min'];
                    }
                    if (isset($config['max'])) {
                        $fieldRules[] = 'max:' . $config['max'];
                    }
                    break;

                case 'json':
                    $fieldRules[] = 'array';
                    break;

                default: // string
                    $fieldRules[] = 'string';
                    if (isset($config['min_length'])) {
                        $fieldRules[] = 'min:' . $config['min_length'];
                    }
                    if (isset($config['max_length'])) {
                        $fieldRules[] = 'max:' . $config['max_length'];
                    }
                    break;
            }

            $rules["settings.{$key}.value"] = $fieldRules;
            $rules["settings.{$key}.type"] = [
                'sometimes',
                'string',
                'in:string,integer,boolean,json,float'
            ];
        }

        return $rules;
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        $availableSettings = PlayerSettings::getAvailableSettings();
        $attributes = [
            'settings' => 'configurações',
        ];

        foreach ($availableSettings as $key => $config) {
            $fieldName = $config['name'];
            $attributes["settings.{$key}.value"] = strtolower($fieldName);
        }

        return $attributes;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'As configurações são obrigatórias.',
            'settings.array' => 'As configurações devem ser um array.',
            '*.sometimes' => 'O campo :attribute é inválido.',
            '*.integer' => 'O campo :attribute deve ser um número inteiro.',
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            '*.numeric' => 'O campo :attribute deve ser um número.',
            '*.string' => 'O campo :attribute deve ser um texto.',
            '*.array' => 'O campo :attribute deve ser um array.',
            '*.min' => 'O campo :attribute deve ser no mínimo :min.',
            '*.max' => 'O campo :attribute deve ser no máximo :max.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $settings = $this->input('settings', []);
            $availableSettings = PlayerSettings::getAvailableSettings();

            // Validate that only known settings are being updated
            foreach ($settings as $key => $data) {
                if (!isset($availableSettings[$key])) {
                    $validator->errors()->add(
                        "settings.{$key}",
                        "Configuração '{$key}' não é suportada."
                    );
                    continue;
                }

                $config = $availableSettings[$key];
                $value = $data['value'] ?? $data;

                // Custom validation for specific settings
                $customValidation = PlayerSettings::validateSetting($key, $value);
                if (!$customValidation['valid']) {
                    foreach ($customValidation['errors'] as $error) {
                        $validator->errors()->add(
                            "settings.{$key}.value",
                            $error
                        );
                    }
                }

                // Special validations
                if ($key === 'visual_theme' && is_array($value)) {
                    // Validate color fields in theme
                    $colorFields = ['primary_color', 'secondary_color', 'background_color', 'text_color'];
                    foreach ($colorFields as $colorField) {
                        if (isset($value[$colorField]) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $value[$colorField])) {
                            $validator->errors()->add(
                                "settings.{$key}.value.{$colorField}",
                                "A cor '{$colorField}' deve estar no formato hexadecimal (ex: #3b82f6)."
                            );
                        }
                    }

                    // Validate logo URL if provided
                    if (isset($value['logo_url']) && !empty($value['logo_url'])) {
                        if (!filter_var($value['logo_url'], FILTER_VALIDATE_URL)) {
                            $validator->errors()->add(
                                "settings.{$key}.value.logo_url",
                                'A URL do logo deve ser válida.'
                            );
                        }
                    }
                }

                // Validate time format for night mode settings
                if (($key === 'night_mode_start' || $key === 'night_mode_end') && is_string($value)) {
                    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
                        $validator->errors()->add(
                            "settings.{$key}.value",
                            'O horário deve estar no formato HH:MM (24 horas).'
                        );
                    }
                }

                // Validate password strength
                if ($key === 'access_password' && !empty($value)) {
                    if (strlen($value) < 4) {
                        $validator->errors()->add(
                            "settings.{$key}.value",
                            'A senha deve ter pelo menos 4 caracteres.'
                        );
                    }

                    // Check for common weak passwords
                    $weakPasswords = ['1234', 'password', 'admin', '0000', '1111'];
                    if (in_array(strtolower($value), $weakPasswords)) {
                        $validator->errors()->add(
                            "settings.{$key}.value",
                            'Esta senha é muito comum. Escolha uma senha mais segura.'
                        );
                    }
                }
            }
        });
    }
}