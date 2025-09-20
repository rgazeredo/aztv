<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerLog extends Model
{
    use HasFactory;

    public $timestamps = false; // Only has created_at

    protected $fillable = [
        'player_id',
        'type',
        'message',
        'data',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($log) {
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

    // Scopes
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeForPlayer($query, $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    // Static methods for logging
    public static function logInfo($playerId, $message, $data = null)
    {
        return static::create([
            'player_id' => $playerId,
            'type' => 'info',
            'message' => $message,
            'data' => $data,
        ]);
    }

    public static function logError($playerId, $message, $data = null)
    {
        return static::create([
            'player_id' => $playerId,
            'type' => 'error',
            'message' => $message,
            'data' => $data,
        ]);
    }

    public static function logHeartbeat($playerId, $data = null)
    {
        return static::create([
            'player_id' => $playerId,
            'type' => 'heartbeat',
            'message' => 'Player heartbeat',
            'data' => $data,
        ]);
    }

    public static function logMediaPlayed($playerId, $mediaFile, $data = null)
    {
        return static::create([
            'player_id' => $playerId,
            'type' => 'media_played',
            'message' => "Media played: {$mediaFile}",
            'data' => $data,
        ]);
    }
}
