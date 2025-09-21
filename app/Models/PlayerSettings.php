<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'setting_key',
        'setting_value',
        'setting_type',
        'is_inherited',
    ];

    protected $casts = [
        'is_inherited' => 'boolean',
    ];

    // Relationships
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    // Scopes
    public function scopeForPlayer($query, int $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeBySetting($query, string $key)
    {
        return $query->where('setting_key', $key);
    }

    public function scopeCustomOnly($query)
    {
        return $query->where('is_inherited', false);
    }

    // Accessors & Mutators
    public function getSettingValueAttribute($value)
    {
        // Cast based on type
        return match($this->setting_type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'float' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function setSettingValueAttribute($value)
    {
        // Convert to string for storage
        $stringValue = match($this->setting_type ?? 'string') {
            'json' => is_array($value) || is_object($value) ? json_encode($value) : $value,
            'boolean' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            default => is_array($value) ? json_encode($value) : (string) $value,
        };

        $this->attributes['setting_value'] = $stringValue;
    }

    // Helper methods
    public function getValueOrDefault($default = null)
    {
        return $this->setting_value ?? $default;
    }

    public static function setValue(int $playerId, string $key, $value, string $type = 'string', bool $isInherited = false)
    {
        return static::updateOrCreate(
            [
                'player_id' => $playerId,
                'setting_key' => $key,
            ],
            [
                'setting_value' => $value,
                'setting_type' => $type,
                'is_inherited' => $isInherited,
            ]
        );
    }

    public static function getValue(int $playerId, string $key, $default = null)
    {
        $setting = static::forPlayer($playerId)->bySetting($key)->first();
        return $setting ? $setting->getValueOrDefault($default) : $default;
    }

    public static function removeSetting(int $playerId, string $key): bool
    {
        return static::forPlayer($playerId)->bySetting($key)->delete() > 0;
    }

    // Available player settings configuration
    public static function getAvailableSettings(): array
    {
        return [
            'volume' => [
                'name' => 'Volume',
                'description' => 'Volume padrão do player (0-100)',
                'type' => 'integer',
                'default' => 80,
                'min' => 0,
                'max' => 100,
                'category' => 'audio',
                'inheritable' => true,
            ],
            'media_interval' => [
                'name' => 'Intervalo entre Mídias',
                'description' => 'Tempo em segundos entre a exibição de mídias',
                'type' => 'integer',
                'default' => 10,
                'min' => 1,
                'max' => 3600,
                'category' => 'playback',
                'inheritable' => true,
            ],
            'loop_enabled' => [
                'name' => 'Loop Contínuo',
                'description' => 'Repetir playlist automaticamente quando chegar ao fim',
                'type' => 'boolean',
                'default' => true,
                'category' => 'playback',
                'inheritable' => true,
            ],
            'access_password' => [
                'name' => 'Senha de Acesso',
                'description' => 'Senha para acessar configurações do player',
                'type' => 'string',
                'default' => null,
                'min_length' => 4,
                'max_length' => 50,
                'category' => 'security',
                'inheritable' => false,
            ],
            'visual_theme' => [
                'name' => 'Tema Visual',
                'description' => 'Configurações de aparência personalizada do player',
                'type' => 'json',
                'default' => [
                    'primary_color' => '#3b82f6',
                    'secondary_color' => '#64748b',
                    'background_color' => '#ffffff',
                    'text_color' => '#1f2937',
                    'font_family' => 'Inter',
                    'logo_url' => null,
                ],
                'category' => 'appearance',
                'inheritable' => true,
            ],
            'auto_brightness' => [
                'name' => 'Brilho Automático',
                'description' => 'Ajustar brilho automaticamente baseado no horário',
                'type' => 'boolean',
                'default' => false,
                'category' => 'display',
                'inheritable' => true,
            ],
            'night_mode_start' => [
                'name' => 'Início do Modo Noturno',
                'description' => 'Horário de início do modo noturno (formato HH:MM)',
                'type' => 'string',
                'default' => '22:00',
                'category' => 'display',
                'inheritable' => true,
            ],
            'night_mode_end' => [
                'name' => 'Fim do Modo Noturno',
                'description' => 'Horário de fim do modo noturno (formato HH:MM)',
                'type' => 'string',
                'default' => '06:00',
                'category' => 'display',
                'inheritable' => true,
            ],
            'auto_update_enabled' => [
                'name' => 'Atualização Automática',
                'description' => 'Permitir atualizações automáticas do player',
                'type' => 'boolean',
                'default' => true,
                'category' => 'system',
                'inheritable' => true,
            ],
            'screenshot_interval' => [
                'name' => 'Intervalo de Screenshots',
                'description' => 'Intervalo em minutos para captura automática de tela (0 = desabilitado)',
                'type' => 'integer',
                'default' => 0,
                'min' => 0,
                'max' => 1440,
                'category' => 'monitoring',
                'inheritable' => true,
            ],
        ];
    }

    // Get settings grouped by category
    public static function getSettingsByCategory(): array
    {
        $settings = static::getAvailableSettings();
        $grouped = [];

        foreach ($settings as $key => $config) {
            $category = $config['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [
                    'name' => static::getCategoryName($category),
                    'settings' => [],
                ];
            }
            $grouped[$category]['settings'][$key] = $config;
        }

        return $grouped;
    }

    // Get human-readable category names
    private static function getCategoryName(string $category): string
    {
        return match($category) {
            'audio' => 'Áudio',
            'playback' => 'Reprodução',
            'security' => 'Segurança',
            'appearance' => 'Aparência',
            'display' => 'Exibição',
            'system' => 'Sistema',
            'monitoring' => 'Monitoramento',
            default => ucfirst($category),
        };
    }

    // Validate setting value
    public static function validateSetting(string $key, $value): array
    {
        $settings = static::getAvailableSettings();

        if (!isset($settings[$key])) {
            return ['valid' => false, 'error' => "Setting '{$key}' não é suportado"];
        }

        $config = $settings[$key];
        $errors = [];

        // Type validation
        switch ($config['type']) {
            case 'integer':
                if (!is_numeric($value)) {
                    $errors[] = 'Deve ser um número inteiro';
                } else {
                    $value = (int) $value;
                    if (isset($config['min']) && $value < $config['min']) {
                        $errors[] = "Valor mínimo é {$config['min']}";
                    }
                    if (isset($config['max']) && $value > $config['max']) {
                        $errors[] = "Valor máximo é {$config['max']}";
                    }
                }
                break;

            case 'boolean':
                if (!is_bool($value) && !in_array($value, ['true', 'false', '1', '0', 1, 0])) {
                    $errors[] = 'Deve ser verdadeiro ou falso';
                }
                break;

            case 'string':
                if (!is_string($value)) {
                    $errors[] = 'Deve ser um texto';
                } else {
                    if (isset($config['min_length']) && strlen($value) < $config['min_length']) {
                        $errors[] = "Comprimento mínimo é {$config['min_length']} caracteres";
                    }
                    if (isset($config['max_length']) && strlen($value) > $config['max_length']) {
                        $errors[] = "Comprimento máximo é {$config['max_length']} caracteres";
                    }
                }
                break;

            case 'json':
                if (is_string($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = 'Deve ser um JSON válido';
                    }
                } elseif (!is_array($value) && !is_object($value)) {
                    $errors[] = 'Deve ser um array ou objeto';
                }
                break;
        }

        // Custom validation
        if ($key === 'night_mode_start' || $key === 'night_mode_end') {
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
                $errors[] = 'Deve estar no formato HH:MM (24 horas)';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'value' => $value,
        ];
    }
}