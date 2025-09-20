<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'player_limit',
        'storage_limit_gb',
        'price',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getActiveTenantsCount(): int
    {
        return $this->tenants()->active()->count();
    }

    public function canAddPlayer(Tenant $tenant): bool
    {
        return $tenant->players()->count() < $this->player_limit;
    }

    public function canUploadFile(Tenant $tenant, int $fileSizeBytes): bool
    {
        $currentUsage = $this->calculateStorageUsage($tenant);
        $fileSizeGb = $fileSizeBytes / (1024 * 1024 * 1024);

        return ($currentUsage + $fileSizeGb) <= $this->storage_limit_gb;
    }

    public function calculateStorageUsage(Tenant $tenant): float
    {
        $totalBytes = $tenant->mediaFiles()->sum('size');
        return $totalBytes / (1024 * 1024 * 1024);
    }
}
