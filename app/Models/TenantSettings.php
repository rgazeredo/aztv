<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TenantSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'category',
        'key',
        'value',
        'type',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // Accessors & Mutators
    public function getValueAttribute($value)
    {
        // Decrypt if encrypted
        if ($this->is_encrypted && $value) {
            $value = Crypt::decryptString($value);
        }

        // Cast based on type
        return match($this->type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'float' => (float) $value,
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    public function setValueAttribute($value)
    {
        // Convert to string for storage
        $stringValue = match($this->type) {
            'json', 'array' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        // Encrypt if needed
        if ($this->is_encrypted) {
            $stringValue = Crypt::encryptString($stringValue);
        }

        $this->attributes['value'] = $stringValue;
    }

    // Helper methods
    public function getValueOrDefault($default = null)
    {
        return $this->value ?? $default;
    }

    public static function setValue(int $tenantId, string $category, string $key, $value, string $type = 'string', bool $isEncrypted = false)
    {
        return static::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'category' => $category,
                'key' => $key,
            ],
            [
                'value' => $value,
                'type' => $type,
                'is_encrypted' => $isEncrypted,
            ]
        );
    }

    public static function getValue(int $tenantId, string $key, $default = null)
    {
        $setting = static::forTenant($tenantId)->byKey($key)->first();
        return $setting ? $setting->getValueOrDefault($default) : $default;
    }

    public static function getByCategory(int $tenantId, string $category): array
    {
        return static::forTenant($tenantId)
            ->byCategory($category)
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    // Default settings categories
    public static function getDefaultCategories(): array
    {
        return [
            'theme' => [
                'name' => 'Tema Visual',
                'description' => 'Configurações de aparência da interface',
                'settings' => [
                    'mode' => ['type' => 'string', 'default' => 'light', 'options' => ['light', 'dark', 'auto']],
                    'primary_color' => ['type' => 'string', 'default' => '#3b82f6'],
                    'sidebar_style' => ['type' => 'string', 'default' => 'expanded', 'options' => ['expanded', 'collapsed']],
                ],
            ],
            'player_defaults' => [
                'name' => 'Configurações Padrão de Players',
                'description' => 'Valores padrão aplicados a novos players',
                'settings' => [
                    'default_volume' => ['type' => 'integer', 'default' => 80, 'min' => 0, 'max' => 100],
                    'connection_timeout' => ['type' => 'integer', 'default' => 30, 'min' => 10, 'max' => 300],
                    'auto_restart' => ['type' => 'boolean', 'default' => true],
                    'default_display_duration' => ['type' => 'integer', 'default' => 10, 'min' => 1, 'max' => 300],
                ],
            ],
            'notifications' => [
                'name' => 'Preferências de Notificação',
                'description' => 'Configurações de alertas e notificações',
                'settings' => [
                    'email_notifications' => ['type' => 'boolean', 'default' => true],
                    'system_notifications' => ['type' => 'boolean', 'default' => true],
                    'player_offline_alerts' => ['type' => 'boolean', 'default' => true],
                    'storage_limit_alerts' => ['type' => 'boolean', 'default' => true],
                    'digest_frequency' => ['type' => 'string', 'default' => 'daily', 'options' => ['never', 'daily', 'weekly']],
                ],
            ],
            'system' => [
                'name' => 'Configurações do Sistema',
                'description' => 'Configurações técnicas e de funcionamento',
                'settings' => [
                    'timezone' => ['type' => 'string', 'default' => 'America/Sao_Paulo'],
                    'date_format' => ['type' => 'string', 'default' => 'd/m/Y', 'options' => ['d/m/Y', 'Y-m-d', 'm/d/Y']],
                    'time_format' => ['type' => 'string', 'default' => 'H:i', 'options' => ['H:i', 'h:i A']],
                    'currency' => ['type' => 'string', 'default' => 'BRL'],
                    'language' => ['type' => 'string', 'default' => 'pt_BR', 'options' => ['pt_BR', 'en_US']],
                ],
            ],
        ];
    }
}