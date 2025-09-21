<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRule extends Model
{
    use HasFactory;

    const TYPE_PLAYER_OFFLINE = 'player_offline';
    const TYPE_PLAYBACK_ERROR = 'playback_error';
    const TYPE_STORAGE_LIMIT = 'storage_limit';

    protected $fillable = [
        'tenant_id',
        'type',
        'condition',
        'threshold',
        'recipients',
        'is_active',
        'last_triggered_at',
    ];

    protected $casts = [
        'condition' => 'array',
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeReady($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('last_triggered_at')
                          ->orWhere('last_triggered_at', '<=', now()->subMinutes(30));
                    });
    }

    // Helper methods
    public function getTypeName(): string
    {
        return match($this->type) {
            self::TYPE_PLAYER_OFFLINE => 'Player Offline',
            self::TYPE_PLAYBACK_ERROR => 'Erro de Reprodução',
            self::TYPE_STORAGE_LIMIT => 'Limite de Armazenamento',
            default => 'Desconhecido'
        };
    }

    public function getDescription(): string
    {
        return match($this->type) {
            self::TYPE_PLAYER_OFFLINE => "Alertar quando player ficar offline por mais de {$this->threshold} minutos",
            self::TYPE_PLAYBACK_ERROR => "Alertar quando houver {$this->threshold} ou mais erros de reprodução em 1 hora",
            self::TYPE_STORAGE_LIMIT => "Alertar quando uso de armazenamento atingir {$this->threshold}%",
            default => 'Alerta personalizado'
        };
    }

    public function canTrigger(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Cooldown period of 30 minutes
        if ($this->last_triggered_at && $this->last_triggered_at->gt(now()->subMinutes(30))) {
            return false;
        }

        return true;
    }

    public function markAsTriggered(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }

    public function getRecipientsString(): string
    {
        return implode(', ', $this->recipients ?? []);
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_PLAYER_OFFLINE => [
                'name' => 'Player Offline',
                'description' => 'Notificar quando um player ficar offline por muito tempo',
                'threshold_label' => 'Minutos offline',
                'default_threshold' => 15,
            ],
            self::TYPE_PLAYBACK_ERROR => [
                'name' => 'Erro de Reprodução',
                'description' => 'Notificar quando houver muitos erros de reprodução',
                'threshold_label' => 'Número de erros por hora',
                'default_threshold' => 5,
            ],
            self::TYPE_STORAGE_LIMIT => [
                'name' => 'Limite de Armazenamento',
                'description' => 'Notificar quando o uso de armazenamento se aproximar do limite',
                'threshold_label' => 'Percentual do limite (%)',
                'default_threshold' => 85,
            ],
        ];
    }

    public function getConditionSummary(): string
    {
        if (empty($this->condition)) {
            return 'Todas as condições';
        }

        $parts = [];

        if (isset($this->condition['player_ids']) && !empty($this->condition['player_ids'])) {
            $playerCount = count($this->condition['player_ids']);
            $parts[] = "{$playerCount} player(s) específico(s)";
        }

        if (isset($this->condition['include_groups']) && !empty($this->condition['include_groups'])) {
            $groupCount = count($this->condition['include_groups']);
            $parts[] = "{$groupCount} grupo(s)";
        }

        return empty($parts) ? 'Todos os players' : implode(', ', $parts);
    }
}