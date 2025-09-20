<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'text',
        'author',
        'category',
        'is_active',
        'display_duration',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getCategories(): array
    {
        return ['motivacional', 'inspiracional', 'empresarial', 'sucesso', 'liderança'];
    }

    public static function getAvailableCategories(): array
    {
        return [
            'motivacional' => 'Motivacional',
            'inspiracional' => 'Inspiracional',
            'empresarial' => 'Empresarial',
            'sucesso' => 'Sucesso',
            'liderança' => 'Liderança',
        ];
    }
}
