<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use HasFactory, Billable;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'phone',
        'document',
        'address',
        'settings',
        'is_active',
        'plan_id',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'address' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function activeUsers(): HasMany
    {
        return $this->users()->where('email_verified_at', '!=', null);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Billing Methods
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscribed('default');
    }

    public function getSubscriptionStatus(): string
    {
        if ($this->isOnTrial()) {
            return 'trial';
        }

        if ($this->hasActiveSubscription()) {
            return 'active';
        }

        return 'inactive';
    }

    public function canAccessFeature(string $feature): bool
    {
        $settings = $this->settings ?? [];
        $features = $settings['features'] ?? [];

        return in_array($feature, $features);
    }

    public function getUserLimit(): int
    {
        $settings = $this->settings ?? [];
        return $settings['max_users'] ?? 10; // Default limit
    }

    public function isAtUserLimit(): bool
    {
        return $this->users()->count() >= $this->getUserLimit();
    }

    public function getSubscriptionPlan(): string
    {
        if ($this->hasActiveSubscription()) {
            $subscription = $this->subscriptions()->active()->first();
            if ($subscription && $subscription->stripe_price) {
                return match($subscription->stripe_price) {
                    'price_basic' => 'basic',
                    'price_professional' => 'professional',
                    'price_enterprise' => 'enterprise',
                    default => 'basic',
                };
            }
        }

        $settings = $this->settings ?? [];
        return $settings['plan'] ?? 'basic';
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function contentModules(): HasMany
    {
        return $this->hasMany(ContentModule::class);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function getActivePlan(): ?Plan
    {
        return $this->plan ?? Plan::where('name', 'Basic')->first();
    }

    public function getPlayerLimit(): int
    {
        $plan = $this->getActivePlan();
        return $plan ? $plan->player_limit : 1;
    }

    public function getStorageLimitGb(): int
    {
        $plan = $this->getActivePlan();
        return $plan ? $plan->storage_limit_gb : 1;
    }

    public function getCurrentStorageUsage(): float
    {
        $totalBytes = $this->mediaFiles()->sum('size');
        return $totalBytes / (1024 * 1024 * 1024);
    }

    public function getCurrentPlayerCount(): int
    {
        return $this->players()->count();
    }

    public function isAtPlayerLimit(): bool
    {
        return $this->getCurrentPlayerCount() >= $this->getPlayerLimit();
    }

    public function isAtStorageLimit(): bool
    {
        return $this->getCurrentStorageUsage() >= $this->getStorageLimitGb();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
