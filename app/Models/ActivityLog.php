<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForModel($query, $modelType, $modelId = null)
    {
        $query = $query->where('model_type', $modelType);

        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }

        return $query;
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function getModelName(): string
    {
        return class_basename($this->model_type);
    }

    public function getUserName(): string
    {
        return $this->user ? $this->user->name : 'Sistema';
    }

    public function getActionLabel(): string
    {
        return match($this->action) {
            'created' => 'Criado',
            'updated' => 'Atualizado',
            'deleted' => 'Excluído',
            'login' => 'Login',
            'logout' => 'Logout',
            'uploaded' => 'Upload realizado',
            'downloaded' => 'Download realizado',
            'config_changed' => 'Configuração alterada',
            'playlist_modified' => 'Playlist modificada',
            'player_created' => 'Player criado',
            'media_uploaded' => 'Mídia enviada',
            default => ucfirst($this->action),
        };
    }

    public function hasDataChanges(): bool
    {
        return !empty($this->old_values) || !empty($this->new_values);
    }

    public function getChangedFields(): array
    {
        if (!$this->hasDataChanges()) {
            return [];
        }

        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];

        return array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
    }

    public function getFieldChange(string $field): ?array
    {
        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];

        if (!array_key_exists($field, $oldValues) && !array_key_exists($field, $newValues)) {
            return null;
        }

        return [
            'field' => $field,
            'old' => $oldValues[$field] ?? null,
            'new' => $newValues[$field] ?? null,
        ];
    }
}