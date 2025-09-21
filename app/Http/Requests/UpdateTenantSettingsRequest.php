<?php

namespace App\Http\Requests;

use App\Models\TenantSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isClient() || $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $category = $this->input('category');
        $categories = TenantSettings::getDefaultCategories();

        if (!isset($categories[$category])) {
            return [
                'category' => 'required|string|in:' . implode(',', array_keys($categories)),
            ];
        }

        $categoryConfig = $categories[$category];
        $rules = [
            'category' => 'required|string|in:' . implode(',', array_keys($categories)),
            'settings' => 'required|array',
        ];

        // Dynamic validation based on category settings
        foreach ($categoryConfig['settings'] as $key => $config) {
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
                case 'array':
                    $fieldRules[] = 'array';
                    break;

                default: // string
                    $fieldRules[] = 'string';
                    if (isset($config['max_length'])) {
                        $fieldRules[] = 'max:' . $config['max_length'];
                    }
                    break;
            }

            // Options validation
            if (isset($config['options'])) {
                $fieldRules[] = Rule::in($config['options']);
            }

            $rules["settings.{$key}.value"] = $fieldRules;
            $rules["settings.{$key}.type"] = [
                'sometimes',
                'string',
                'in:string,integer,boolean,json,array,float'
            ];
            $rules["settings.{$key}.encrypted"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        $category = $this->input('category');
        $categories = TenantSettings::getDefaultCategories();

        if (!isset($categories[$category])) {
            return [];
        }

        $categoryConfig = $categories[$category];
        $attributes = [
            'category' => 'categoria',
            'settings' => 'configurações',
        ];

        foreach ($categoryConfig['settings'] as $key => $config) {
            $fieldName = $config['name'] ?? ucfirst(str_replace('_', ' ', $key));
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
            'category.required' => 'A categoria é obrigatória.',
            'category.in' => 'A categoria selecionada é inválida.',
            'settings.required' => 'As configurações são obrigatórias.',
            'settings.array' => 'As configurações devem ser um array.',
            '*.sometimes' => 'O campo :attribute é inválido.',
            '*.integer' => 'O campo :attribute deve ser um número inteiro.',
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            '*.numeric' => 'O campo :attribute deve ser um número.',
            '*.string' => 'O campo :attribute deve ser um texto.',
            '*.array' => 'O campo :attribute deve ser um array.',
            '*.in' => 'O valor selecionado para :attribute é inválido.',
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
            $category = $this->input('category');
            $settings = $this->input('settings', []);
            $categories = TenantSettings::getDefaultCategories();

            if (!isset($categories[$category])) {
                return;
            }

            $categoryConfig = $categories[$category];

            // Validate that only known settings are being updated
            foreach ($settings as $key => $data) {
                if (!isset($categoryConfig['settings'][$key])) {
                    $validator->errors()->add(
                        "settings.{$key}",
                        "Configuração '{$key}' não é válida para a categoria '{$category}'."
                    );
                }
            }

            // Custom validation for specific setting types
            foreach ($settings as $key => $data) {
                if (!isset($categoryConfig['settings'][$key])) {
                    continue;
                }

                $config = $categoryConfig['settings'][$key];
                $value = $data['value'] ?? $data;

                // Validate color values
                if ($key === 'primary_color' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    $validator->errors()->add(
                        "settings.{$key}.value",
                        'A cor primária deve estar no formato hexadecimal (ex: #3b82f6).'
                    );
                }

                // Validate timezone
                if ($key === 'timezone' && !in_array($value, timezone_identifiers_list())) {
                    $validator->errors()->add(
                        "settings.{$key}.value",
                        'O fuso horário selecionado não é válido.'
                    );
                }

                // Validate currency codes
                if ($key === 'currency' && !preg_match('/^[A-Z]{3}$/', $value)) {
                    $validator->errors()->add(
                        "settings.{$key}.value",
                        'O código da moeda deve ter 3 letras maiúsculas (ex: BRL, USD).'
                    );
                }
            }
        });
    }
}