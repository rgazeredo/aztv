<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PlayerActivationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'token',
        'activation_code',
        'qr_code_path',
        'short_url',
        'player_id',
        'expires_at',
        'is_used',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($token) {
            if (empty($token->token)) {
                $token->token = $token->generateUniqueToken();
            }

            if (empty($token->activation_code)) {
                $token->activation_code = $token->generateUniqueActivationCode();
            }

            if (empty($token->expires_at)) {
                $token->expires_at = now()->addHours(24);
            }

            if (empty($token->short_url)) {
                $token->short_url = "/a/{$token->token}";
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_used', false)
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeUsed($query)
    {
        return $query->where('is_used', true);
    }

    public function scopeUnused($query)
    {
        return $query->where('is_used', false);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }

    public function markAsUsed(Player $player = null): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => now(),
            'player_id' => $player?->id,
        ]);
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_used) {
            return 'used';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'active';
    }

    public function getExpiresInAttribute(): string
    {
        if ($this->isExpired()) {
            return 'Expirado';
        }

        return $this->expires_at->diffForHumans();
    }

    public function getActivationUrlAttribute(): string
    {
        return url("/activation/{$this->token}");
    }

    public function getQrCodeUrlAttribute(): string
    {
        return url("/activation/{$this->token}/qr");
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::where('token', $token)->exists());

        return $token;
    }

    private function generateUniqueActivationCode(): string
    {
        do {
            $code = str_pad(random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (static::where('activation_code', $code)->exists());

        return $code;
    }

    public static function findByTokenOrCode(string $identifier): ?self
    {
        return static::where('token', $identifier)
                    ->orWhere('activation_code', $identifier)
                    ->first();
    }

    public static function generateForTenant(Tenant $tenant, array $data = []): self
    {
        return static::create([
            'tenant_id' => $tenant->id,
            'expires_at' => $data['expires_at'] ?? now()->addHours(24),
        ]);
    }
}
