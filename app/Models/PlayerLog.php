<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerLog extends Model
{
    use HasFactory;

    const EVENT_MEDIA_START = 'MEDIA_START';
    const EVENT_MEDIA_END = 'MEDIA_END';
    const EVENT_MEDIA_ERROR = 'MEDIA_ERROR';
    const EVENT_CONNECTION_ERROR = 'CONNECTION_ERROR';
    const EVENT_PERFORMANCE_METRIC = 'PERFORMANCE_METRIC';
    const EVENT_HEARTBEAT = 'HEARTBEAT';
    const EVENT_INFO = 'INFO';
    const EVENT_ERROR = 'ERROR';

    protected $fillable = [
        'player_id',
        'tenant_id',
        'event_type',
        'event_data',
        'media_file_id',
        'timestamp',
        'ip_address',
        'user_agent',
        'type', // backward compatibility
        'message', // backward compatibility
        'data', // backward compatibility
    ];

    protected $casts = [
        'event_data' => 'array',
        'timestamp' => 'datetime',
        'data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($log) {
            if (!$log->timestamp) {
                $log->timestamp = now();
            }
            if (!$log->created_at) {
                $log->created_at = now();
            }
        });
    }

    // Relationships
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    // Scopes
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('timestamp', '>=', now()->subHours($hours));
    }

    public function scopeForPlayer($query, $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMediaFile($query, $mediaFileId)
    {
        return $query->where('media_file_id', $mediaFileId);
    }

    public function scopeMediaEvents($query)
    {
        return $query->whereIn('event_type', [
            self::EVENT_MEDIA_START,
            self::EVENT_MEDIA_END,
            self::EVENT_MEDIA_ERROR
        ]);
    }

    public function scopePerformanceEvents($query)
    {
        return $query->where('event_type', self::EVENT_PERFORMANCE_METRIC);
    }

    public function scopeErrorEvents($query)
    {
        return $query->whereIn('event_type', [
            self::EVENT_MEDIA_ERROR,
            self::EVENT_CONNECTION_ERROR,
            self::EVENT_ERROR
        ]);
    }

    // Helper methods
    public function getEventTypeName(): string
    {
        return match($this->event_type) {
            self::EVENT_MEDIA_START => 'Início de Reprodução',
            self::EVENT_MEDIA_END => 'Fim de Reprodução',
            self::EVENT_MEDIA_ERROR => 'Erro de Mídia',
            self::EVENT_CONNECTION_ERROR => 'Erro de Conexão',
            self::EVENT_PERFORMANCE_METRIC => 'Métrica de Performance',
            self::EVENT_HEARTBEAT => 'Heartbeat',
            self::EVENT_INFO => 'Informação',
            self::EVENT_ERROR => 'Erro',
            default => ucfirst(str_replace('_', ' ', strtolower($this->event_type ?? 'unknown')))
        };
    }

    public function isErrorEvent(): bool
    {
        return in_array($this->event_type, [
            self::EVENT_MEDIA_ERROR,
            self::EVENT_CONNECTION_ERROR,
            self::EVENT_ERROR
        ]);
    }

    public function isMediaEvent(): bool
    {
        return in_array($this->event_type, [
            self::EVENT_MEDIA_START,
            self::EVENT_MEDIA_END,
            self::EVENT_MEDIA_ERROR
        ]);
    }

    // Static methods for logging (backward compatibility)
    public static function logInfo($playerId, $message, $data = null)
    {
        $player = Player::find($playerId);
        return static::create([
            'player_id' => $playerId,
            'tenant_id' => $player?->tenant_id,
            'event_type' => self::EVENT_INFO,
            'event_data' => ['message' => $message, 'data' => $data],
            'type' => 'info',
            'message' => $message,
            'data' => $data,
        ]);
    }

    public static function logError($playerId, $message, $data = null)
    {
        $player = Player::find($playerId);
        return static::create([
            'player_id' => $playerId,
            'tenant_id' => $player?->tenant_id,
            'event_type' => self::EVENT_ERROR,
            'event_data' => ['message' => $message, 'data' => $data],
            'type' => 'error',
            'message' => $message,
            'data' => $data,
        ]);
    }

    public static function logHeartbeat($playerId, $data = null)
    {
        $player = Player::find($playerId);
        return static::create([
            'player_id' => $playerId,
            'tenant_id' => $player?->tenant_id,
            'event_type' => self::EVENT_HEARTBEAT,
            'event_data' => $data,
            'type' => 'heartbeat',
            'message' => 'Player heartbeat',
            'data' => $data,
        ]);
    }

    public static function logMediaPlayed($playerId, $mediaFile, $data = null)
    {
        $player = Player::find($playerId);
        return static::create([
            'player_id' => $playerId,
            'tenant_id' => $player?->tenant_id,
            'event_type' => self::EVENT_MEDIA_START,
            'event_data' => $data,
            'type' => 'media_played',
            'message' => "Media played: {$mediaFile}",
            'data' => $data,
        ]);
    }
}
