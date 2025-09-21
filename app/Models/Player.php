<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\QRCodeService;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'alias',
        'location',
        'group',
        'status',
        'ip_address',
        'last_seen_at',
        'app_version',
        'device_info',
        'activation_token',
        'settings',
    ];

    protected $casts = [
        'device_info' => 'array',
        'settings' => 'array',
        'last_seen_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($player) {
            if (empty($player->activation_token)) {
                $player->activation_token = Str::random(32);
            }
        });
    }

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'player_playlists')
            ->withPivot(['priority', 'start_date', 'end_date', 'schedule_config'])
            ->withTimestamps()
            ->orderBy('pivot_priority');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PlayerLog::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(PlayerAlert::class);
    }

    // Methods
    public function isOnline(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->diffInMinutes(now()) <= 5; // Consider online if seen within 5 minutes
    }

    public function getStatus(): string
    {
        if ($this->status === 'active' && $this->isOnline()) {
            return 'online';
        }

        if ($this->status === 'active' && !$this->isOnline()) {
            return 'offline';
        }

        return $this->status;
    }

    public function updateLastSeen(): void
    {
        $this->update([
            'last_seen_at' => now(),
            'status' => 'active'
        ]);
    }

    public function generateNewActivationToken(): string
    {
        $token = Str::random(32);
        $this->update(['activation_token' => $token]);
        return $token;
    }

    public function getActivePlaylists()
    {
        return $this->playlists()
            ->wherePivot(function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('start_date')
                      ->orWhere('start_date', '<=', now()->toDateString());
                })
                ->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now()->toDateString());
                });
            })
            ->get();
    }

    // Scopes
    public function scopeOnline($query)
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes(5))
                    ->where('status', 'active');
    }

    public function scopeOffline($query)
    {
        return $query->where(function ($q) {
            $q->where('last_seen_at', '<', now()->subMinutes(5))
              ->orWhereNull('last_seen_at')
              ->orWhere('status', '!=', 'active');
        });
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // QR Code and Activation Methods
    public function getActivationUrl(): string
    {
        return config('app.url') . "/api/player/activate?token={$this->activation_token}";
    }

    public function generateQRCode(array $options = []): string
    {
        $qrCodeService = app(QRCodeService::class);
        return $qrCodeService->savePlayerQRCode($this, $options);
    }

    public function getQRCodeUrl(): ?string
    {
        $qrCodeService = app(QRCodeService::class);
        return $qrCodeService->getPlayerQRCodeUrl($this);
    }

    public function hasQRCode(): bool
    {
        $qrCodeService = app(QRCodeService::class);
        return $qrCodeService->playerHasQRCode($this);
    }

    public function deleteQRCode(): bool
    {
        $qrCodeService = app(QRCodeService::class);
        return $qrCodeService->deletePlayerQRCode($this);
    }

    protected static function booted()
    {
        static::created(function ($player) {
            // Automatically generate QR code after player creation
            $player->generateQRCode();
        });

        static::updated(function ($player) {
            // Regenerate QR code if activation token changed
            if ($player->isDirty('activation_token')) {
                $player->deleteQRCode();
                $player->generateQRCode();
            }
        });
    }
}
