<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'token',
        'activation_token',
        'remember_token',
        'api_token',
        'stripe_id',
        'pm_last_four',
    ];

    public function log(
        string $action,
        Model $model = null,
        array $oldValues = null,
        array $newValues = null,
        string $description = null
    ): ActivityLog {
        $user = Auth::user();
        $tenant = $this->getCurrentTenant();

        $logData = [
            'user_id' => $user?->id,
            'tenant_id' => $tenant?->id,
            'action' => $action,
            'old_values' => $oldValues ? $this->sanitizeData($oldValues) : null,
            'new_values' => $newValues ? $this->sanitizeData($newValues) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => $description,
        ];

        if ($model) {
            $logData['model_type'] = get_class($model);
            $logData['model_id'] = $model->getKey();
        }

        return ActivityLog::create($logData);
    }

    public function logPlayerCreated(Model $player): ActivityLog
    {
        return $this->log(
            'player_created',
            $player,
            null,
            $player->toArray(),
            "Player '{$player->name}' foi criado"
        );
    }

    public function logPlayerUpdated(Model $player, array $oldValues): ActivityLog
    {
        return $this->log(
            'updated',
            $player,
            $oldValues,
            $player->toArray(),
            "Player '{$player->name}' foi atualizado"
        );
    }

    public function logPlayerDeleted(Model $player): ActivityLog
    {
        return $this->log(
            'deleted',
            $player,
            $player->toArray(),
            null,
            "Player '{$player->name}' foi excluído"
        );
    }

    public function logMediaUploaded(Model $mediaFile): ActivityLog
    {
        return $this->log(
            'media_uploaded',
            $mediaFile,
            null,
            $mediaFile->toArray(),
            "Mídia '{$mediaFile->original_name}' foi enviada"
        );
    }

    public function logMediaUpdated(Model $mediaFile, array $oldValues): ActivityLog
    {
        return $this->log(
            'updated',
            $mediaFile,
            $oldValues,
            $mediaFile->toArray(),
            "Mídia '{$mediaFile->original_name}' foi atualizada"
        );
    }

    public function logMediaDeleted(Model $mediaFile): ActivityLog
    {
        return $this->log(
            'deleted',
            $mediaFile,
            $mediaFile->toArray(),
            null,
            "Mídia '{$mediaFile->original_name}' foi excluída"
        );
    }

    public function logPlaylistCreated(Model $playlist): ActivityLog
    {
        return $this->log(
            'created',
            $playlist,
            null,
            $playlist->toArray(),
            "Playlist '{$playlist->name}' foi criada"
        );
    }

    public function logPlaylistModified(Model $playlist, array $oldValues, string $specificAction = null): ActivityLog
    {
        $description = $specificAction ?: "Playlist '{$playlist->name}' foi modificada";

        return $this->log(
            'playlist_modified',
            $playlist,
            $oldValues,
            $playlist->toArray(),
            $description
        );
    }

    public function logPlaylistDeleted(Model $playlist): ActivityLog
    {
        return $this->log(
            'deleted',
            $playlist,
            $playlist->toArray(),
            null,
            "Playlist '{$playlist->name}' foi excluída"
        );
    }

    public function logConfigChanged(string $configKey, $oldValue, $newValue, Model $model = null): ActivityLog
    {
        return $this->log(
            'config_changed',
            $model,
            [$configKey => $oldValue],
            [$configKey => $newValue],
            "Configuração '{$configKey}' foi alterada"
        );
    }

    public function logLogin(User $user): ActivityLog
    {
        return $this->log(
            'login',
            $user,
            null,
            null,
            "Usuário '{$user->name}' fez login"
        );
    }

    public function logLogout(User $user): ActivityLog
    {
        return $this->log(
            'logout',
            $user,
            null,
            null,
            "Usuário '{$user->name}' fez logout"
        );
    }

    public function logUserCreated(User $user): ActivityLog
    {
        return $this->log(
            'created',
            $user,
            null,
            $this->sanitizeData($user->toArray()),
            "Usuário '{$user->name}' foi criado"
        );
    }

    public function logUserUpdated(User $user, array $oldValues): ActivityLog
    {
        return $this->log(
            'updated',
            $user,
            $oldValues,
            $this->sanitizeData($user->toArray()),
            "Usuário '{$user->name}' foi atualizado"
        );
    }

    public function logBulkAction(string $action, string $modelType, array $modelIds, array $additionalData = []): ActivityLog
    {
        $description = sprintf(
            'Ação em lote: %s executada em %d %s(s)',
            $action,
            count($modelIds),
            class_basename($modelType)
        );

        return $this->log(
            'bulk_action',
            null,
            ['action' => $action, 'model_type' => $modelType, 'model_ids' => $modelIds],
            $additionalData,
            $description
        );
    }

    protected function sanitizeData(array $data): array
    {
        foreach ($this->sensitiveFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }

    protected function getCurrentTenant(): ?Tenant
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        return $user->tenant;
    }

    public function getRecentActivity(int $tenantId, int $days = 7, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return ActivityLog::forTenant($tenantId)
            ->with(['user', 'subject'])
            ->recent($days)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUserActivity(int $userId, int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return ActivityLog::forUser($userId)
            ->with(['subject'])
            ->recent($days)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getModelActivity(string $modelType, int $modelId): \Illuminate\Database\Eloquent\Collection
    {
        return ActivityLog::forModel($modelType, $modelId)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}