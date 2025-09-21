<?php

namespace App\Http\Requests;

use App\Models\AlertRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('alertRule'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    AlertRule::TYPE_PLAYER_OFFLINE,
                    AlertRule::TYPE_PLAYBACK_ERROR,
                    AlertRule::TYPE_STORAGE_LIMIT,
                ])
            ],
            'threshold' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    if ($this->type === AlertRule::TYPE_STORAGE_LIMIT && ($value < 1 || $value > 100)) {
                        $fail('O limite para alertas de armazenamento deve estar entre 1 e 100%.');
                    }
                }
            ],
            'recipients' => [
                'required',
                'array',
                'min:1',
            ],
            'recipients.*' => [
                'required',
                'email',
            ],
            'condition' => [
                'nullable',
                'array',
            ],
            'condition.player_ids' => [
                'nullable',
                'array',
            ],
            'condition.player_ids.*' => [
                'required',
                'integer',
                'exists:players,id',
            ],
            'condition.include_groups' => [
                'nullable',
                'array',
            ],
            'condition.include_groups.*' => [
                'required',
                'string',
            ],
            'is_active' => [
                'boolean',
            ],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo de alerta',
            'threshold' => 'limite',
            'recipients' => 'destinatários',
            'recipients.*' => 'email do destinatário',
            'condition.player_ids' => 'players selecionados',
            'condition.include_groups' => 'grupos incluídos',
            'is_active' => 'status ativo',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'O tipo de alerta é obrigatório.',
            'type.in' => 'O tipo de alerta selecionado é inválido.',
            'threshold.required' => 'O limite é obrigatório.',
            'threshold.integer' => 'O limite deve ser um número inteiro.',
            'threshold.min' => 'O limite deve ser maior que zero.',
            'recipients.required' => 'Pelo menos um destinatário é obrigatório.',
            'recipients.min' => 'Pelo menos um destinatário é obrigatório.',
            'recipients.*.required' => 'O email do destinatário é obrigatório.',
            'recipients.*.email' => 'O email do destinatário deve ser um endereço válido.',
            'condition.player_ids.*.exists' => 'Um dos players selecionados não existe.',
        ];
    }
}