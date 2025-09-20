<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FileValidationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'original_filename',
        'mime_type',
        'file_size',
        'validation_status',
        'rejection_reason',
        'warnings',
        'user_agent',
        'ip_address',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeFailed($query)
    {
        return $query->where('validation_status', 'failed');
    }

    public function scopePassed($query)
    {
        return $query->where('validation_status', 'passed');
    }

    public function scopeWithWarnings($query)
    {
        return $query->where('validation_status', 'warning')
                     ->orWhereNotNull('warnings');
    }

    public function scopeSuspiciousActivity($query)
    {
        return $query->where('validation_status', 'failed')
                     ->orWhereNotNull('warnings');
    }

    public function scopeFromIp($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Helper methods
    public function getFormattedFileSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function isSuspicious(): bool
    {
        return $this->validation_status === 'failed' || !empty($this->warnings);
    }

    public function hasSecurityWarnings(): bool
    {
        if (empty($this->warnings)) {
            return false;
        }

        $securityWarnings = [
            'JavaScript code detected',
            'Iframe element detected',
            'Eval function detected',
            'Base64 decode function detected',
            'Execution function detected',
            'System function detected',
            'Shell execution function detected',
        ];

        foreach ($securityWarnings as $warning) {
            if (str_contains($this->warnings, $warning)) {
                return true;
            }
        }

        return false;
    }

    // Static methods for reporting
    public static function getFailureRate(int $tenantId, int $hours = 24): float
    {
        $total = static::forTenant($tenantId)->recent($hours)->count();
        $failed = static::forTenant($tenantId)->recent($hours)->failed()->count();

        return $total > 0 ? ($failed / $total) * 100 : 0;
    }

    public static function getSuspiciousIps(int $tenantId, int $hours = 24): array
    {
        return static::forTenant($tenantId)
                    ->recent($hours)
                    ->suspiciousActivity()
                    ->groupBy('ip_address')
                    ->selectRaw('ip_address, COUNT(*) as attempts')
                    ->having('attempts', '>=', 3)
                    ->pluck('attempts', 'ip_address')
                    ->toArray();
    }

    public static function getRecentSecurityWarnings(int $tenantId, int $hours = 24): array
    {
        return static::forTenant($tenantId)
                    ->recent($hours)
                    ->whereNotNull('warnings')
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get()
                    ->filter(fn($log) => $log->hasSecurityWarnings())
                    ->values()
                    ->toArray();
    }
}
