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
use Illuminate\Support\Facades\Cache;

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
        'last_seen',
        'app_version',
        'device_info',
        'activation_token',
        'settings',
    ];

    protected $casts = [
        'device_info' => 'array',
        'settings' => 'array',
        'last_seen' => 'datetime',
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
        if (!$this->last_seen) {
            return false;
        }

        return $this->last_seen->diffInMinutes(now()) <= 5; // Consider online if seen within 5 minutes
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
            'last_seen' => now(),
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
            ->with(['items' => function ($q) {
                $q->orderBy('order');
            }, 'items.mediaFile:id,name,filename,mime_type,file_size,duration,file_path,checksum'])
            ->where(function ($query) {
                $query->whereNull('player_playlists.start_date')
                      ->orWhere('player_playlists.start_date', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('player_playlists.end_date')
                      ->orWhere('player_playlists.end_date', '>=', now()->toDateString());
            })
            ->get();
    }

    // Scopes
    public function scopeOnline($query)
    {
        return $query->where('last_seen', '>=', now()->subMinutes(5))
                    ->where('status', 'active');
    }

    public function scopeOffline($query)
    {
        return $query->where(function ($q) {
            $q->where('last_seen', '<', now()->subMinutes(5))
              ->orWhereNull('last_seen')
              ->orWhere('status', '!=', 'active');
        });
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope for loading sync data with optimized eager loading
     */
    public function scopeWithSyncData($query)
    {
        return $query->with([
            'tenant:id,name',
            'playlists' => function ($q) {
                $q->where(function ($query) {
                    $query->whereNull('player_playlists.start_date')
                          ->orWhere('player_playlists.start_date', '<=', now()->toDateString());
                })
                ->where(function ($query) {
                    $query->whereNull('player_playlists.end_date')
                          ->orWhere('player_playlists.end_date', '>=', now()->toDateString());
                });
            },
            'playlists.items' => function ($q) {
                $q->orderBy('order');
            },
            'playlists.items.mediaFile:id,name,filename,mime_type,file_size,duration,file_path,checksum',
            'logs' => function ($q) {
                $q->latest()->limit(10);
            }
        ]);
    }

    /**
     * Scope for loading basic player data for status checks
     */
    public function scopeWithStatusData($query)
    {
        return $query->with([
            'tenant:id,name'
        ]);
    }

    /**
     * Scope for loading full player configuration
     */
    public function scopeWithFullData($query)
    {
        return $query->with([
            'tenant',
            'playlists.items.mediaFile',
            'logs' => function ($q) {
                $q->latest()->limit(20);
            },
            'alerts' => function ($q) {
                $q->latest()->limit(10);
            }
        ]);
    }

    /**
     * Scope for active players with efficient joins
     */
    public function scopeActiveWithTenant($query, $tenantId = null)
    {
        $query = $query->where('status', 'active')
                      ->where('last_seen', '>=', now()->subMinutes(5));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->with('tenant:id,name');
    }

    /**
     * Cache frequently accessed playlists for 5 minutes
     */
    public function getCachedActivePlaylists()
    {
        $cacheKey = "player_active_playlists_{$this->id}";

        return Cache::remember($cacheKey, 300, function () {
            return $this->getActivePlaylists();
        });
    }

    /**
     * Cache player settings for 1 hour
     */
    public function getCachedSettings()
    {
        $cacheKey = "player_settings_{$this->id}";

        return Cache::remember($cacheKey, 3600, function () {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'settings' => $this->settings,
                'tenant_id' => $this->tenant_id,
                'status' => $this->status,
                'last_seen' => $this->last_seen?->toISOString(),
            ];
        });
    }

    /**
     * Invalidate all caches for this player
     */
    public function invalidateCache(): void
    {
        Cache::forget("player_active_playlists_{$this->id}");
        Cache::forget("player_settings_{$this->id}");
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

            // Invalidate cache when player data changes
            if ($player->isDirty(['settings', 'status', 'last_seen'])) {
                $player->invalidateCache();
            }
        });
    }
}
