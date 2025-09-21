<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'tenant_id',
        'alert_type',
        'message',
        'player_last_seen_at',
        'resolved',
        'resolved_at',
    ];

    protected $casts = [
        'player_last_seen_at' => 'datetime',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    // Relationships
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    public function scopeResolved($query)
    {
        return $query->where('resolved', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('alert_type', $type);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPlayer($query, $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeRecent($query, int $minutes = 30)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    // Methods
    public function markAsResolved(): bool
    {
        return $this->update([
            'resolved' => true,
            'resolved_at' => now(),
        ]);
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function getAgeInMinutes(): int
    {
        return $this->created_at->diffInMinutes(now());
    }

    public function getFormattedMessage(): string
    {
        return str_replace(
            ['{player_name}', '{last_seen}', '{duration}'],
            [
                $this->player->name ?? 'Unknown Player',
                $this->player_last_seen_at?->format('d/m/Y H:i') ?? 'Never',
                $this->getAgeInMinutes() . ' minutos'
            ],
            $this->message
        );
    }

    // Static methods
    public static function createOfflineAlert(Player $player): self
    {
        $lastSeen = $player->last_seen_at ?? $player->updated_at;
        $offlineDuration = now()->diffInMinutes($lastSeen);

        return static::create([
            'player_id' => $player->id,
            'tenant_id' => $player->tenant_id,
            'alert_type' => 'offline',
            'message' => "Player '{player_name}' está offline há {duration}. Último contato: {last_seen}",
            'player_last_seen_at' => $lastSeen,
        ]);
    }

    public static function hasRecentAlert(Player $player, int $minutes = 30): bool
    {
        return static::where('player_id', $player->id)
            ->where('alert_type', 'offline')
            ->where('resolved', false)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }

    public static function resolvePlayerAlerts(Player $player): int
    {
        return static::where('player_id', $player->id)
            ->where('resolved', false)
            ->update([
                'resolved' => true,
                'resolved_at' => now(),
            ]);
    }
}