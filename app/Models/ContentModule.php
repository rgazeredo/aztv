<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'type',
        'is_enabled',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    // Constants for module types
    const TYPE_WEATHER = 'weather';
    const TYPE_QUOTES = 'quotes';
    const TYPE_CURRENCY = 'currency';
    const TYPE_HEALTH_TIPS = 'health_tips';
    const TYPE_FUNNY_VIDEOS = 'funny_videos';
    const TYPE_PRICE_TABLE = 'price_table';

    const AVAILABLE_TYPES = [
        self::TYPE_WEATHER,
        self::TYPE_QUOTES,
        self::TYPE_CURRENCY,
        self::TYPE_HEALTH_TIPS,
        self::TYPE_FUNNY_VIDEOS,
        self::TYPE_PRICE_TABLE,
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Methods
    public function enable(): void
    {
        $this->update(['is_enabled' => true]);
    }

    public function disable(): void
    {
        $this->update(['is_enabled' => false]);
    }

    public function toggle(): void
    {
        $this->update(['is_enabled' => !$this->is_enabled]);
    }

    public function updateSettings(array $settings): void
    {
        $this->update(['settings' => array_merge($this->settings ?? [], $settings)]);
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function getDisplayName(): string
    {
        return match($this->type) {
            self::TYPE_WEATHER => 'Previsão do Tempo',
            self::TYPE_QUOTES => 'Frases Motivacionais',
            self::TYPE_CURRENCY => 'Cotação de Moedas',
            self::TYPE_HEALTH_TIPS => 'Dicas de Saúde',
            self::TYPE_FUNNY_VIDEOS => 'Vídeos Engraçados',
            self::TYPE_PRICE_TABLE => 'Tabela de Preços',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function getDescription(): string
    {
        return match($this->type) {
            self::TYPE_WEATHER => 'Exibe previsão do tempo automaticamente',
            self::TYPE_QUOTES => 'Mostra frases motivacionais rotativas',
            self::TYPE_CURRENCY => 'Exibe cotação de moedas (USD, EUR, BTC)',
            self::TYPE_HEALTH_TIPS => 'Apresenta dicas de saúde e bem-estar',
            self::TYPE_FUNNY_VIDEOS => 'Reproduz vídeos engraçados automaticamente',
            self::TYPE_PRICE_TABLE => 'Mostra tabela de preços via Excel',
            default => 'Módulo de conteúdo automático',
        };
    }

    public function getDefaultSettings(): array
    {
        return match($this->type) {
            self::TYPE_WEATHER => [
                'city' => 'São Paulo',
                'api_key' => '',
                'update_interval' => 30, // minutes
                'display_duration' => 10, // seconds
            ],
            self::TYPE_QUOTES => [
                'category' => 'motivational',
                'rotation_interval' => 60, // minutes
                'display_duration' => 15, // seconds
            ],
            self::TYPE_CURRENCY => [
                'currencies' => ['USD', 'EUR', 'BTC'],
                'update_interval' => 15, // minutes
                'display_duration' => 10, // seconds
            ],
            self::TYPE_HEALTH_TIPS => [
                'category' => 'general',
                'rotation_interval' => 120, // minutes
                'display_duration' => 20, // seconds
            ],
            self::TYPE_FUNNY_VIDEOS => [
                'source' => 'youtube',
                'duration_limit' => 60, // seconds
                'rotation_interval' => 180, // minutes
            ],
            self::TYPE_PRICE_TABLE => [
                'excel_file' => '',
                'update_interval' => 60, // minutes
                'display_duration' => 30, // seconds
            ],
            default => [],
        };
    }

    public function hasRequiredSettings(): bool
    {
        $required = match($this->type) {
            self::TYPE_WEATHER => ['api_key'],
            self::TYPE_PRICE_TABLE => ['excel_file'],
            default => [],
        };

        foreach ($required as $setting) {
            if (empty($this->getSetting($setting))) {
                return false;
            }
        }

        return true;
    }

    // Static methods
    public static function createForTenant(int $tenantId, string $type, array $settings = []): self
    {
        $module = static::create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'is_enabled' => false,
            'settings' => array_merge(
                (new static(['type' => $type]))->getDefaultSettings(),
                $settings
            ),
        ]);

        return $module;
    }

    public static function getAvailableTypes(): array
    {
        return self::AVAILABLE_TYPES;
    }

    public static function getTypesWithDescriptions(): array
    {
        $types = [];
        foreach (self::AVAILABLE_TYPES as $type) {
            $module = new static(['type' => $type]);
            $types[$type] = [
                'name' => $module->getDisplayName(),
                'description' => $module->getDescription(),
            ];
        }
        return $types;
    }

    // Scopes
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeDisabled($query)
    {
        return $query->where('is_enabled', false);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeReadyToUse($query)
    {
        return $query->where('is_enabled', true)
            ->get()
            ->filter(function ($module) {
                return $module->hasRequiredSettings();
            });
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        // Set default settings when creating
        static::creating(function ($module) {
            if (empty($module->settings)) {
                $module->settings = $module->getDefaultSettings();
            }
        });
    }
}
