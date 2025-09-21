<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PlaylistSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'playlist_id',
        'tenant_id',
        'name',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'days_of_week',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'days_of_week' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    // Relationships
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyActive($query, ?Carbon $dateTime = null)
    {
        $dateTime = $dateTime ?? now();
        $currentDate = $dateTime->toDateString();
        $currentTime = $dateTime->format('H:i:s');
        $currentDayOfWeek = $dateTime->dayOfWeek; // 0 = Sunday, 6 = Saturday

        return $query->active()
            ->where(function ($query) use ($currentDate) {
                $query->where(function ($q) use ($currentDate) {
                    $q->whereNull('start_date')
                      ->orWhere('start_date', '<=', $currentDate);
                })
                ->where(function ($q) use ($currentDate) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', $currentDate);
                });
            })
            ->where(function ($query) use ($currentTime) {
                $query->where(function ($q) use ($currentTime) {
                    $q->whereNull('start_time')
                      ->orWhere('start_time', '<=', $currentTime);
                })
                ->where(function ($q) use ($currentTime) {
                    $q->whereNull('end_time')
                      ->orWhere('end_time', '>=', $currentTime);
                });
            })
            ->where(function ($query) use ($currentDayOfWeek) {
                $query->whereNull('days_of_week')
                      ->orWhereJsonContains('days_of_week', $currentDayOfWeek);
            });
    }

    public function scopeByDayOfWeek($query, int $dayOfWeek)
    {
        return $query->whereJsonContains('days_of_week', $dayOfWeek);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByPriority($query, string $direction = 'desc')
    {
        return $query->orderBy('priority', $direction);
    }

    // Helper Methods
    public function isActiveAt(Carbon $dateTime): bool
    {
        // Check date range
        if ($this->start_date && $dateTime->toDateString() < $this->start_date->toDateString()) {
            return false;
        }

        if ($this->end_date && $dateTime->toDateString() > $this->end_date->toDateString()) {
            return false;
        }

        // Check time range
        if ($this->start_time && $dateTime->format('H:i:s') < $this->start_time->format('H:i:s')) {
            return false;
        }

        if ($this->end_time && $dateTime->format('H:i:s') > $this->end_time->format('H:i:s')) {
            return false;
        }

        // Check day of week
        if ($this->days_of_week && !in_array($dateTime->dayOfWeek, $this->days_of_week)) {
            return false;
        }

        return $this->is_active;
    }

    public function getFormattedTimeRange(): string
    {
        $start = $this->start_time ? $this->start_time->format('H:i') : 'Sem horário inicial';
        $end = $this->end_time ? $this->end_time->format('H:i') : 'Sem horário final';

        if (!$this->start_time && !$this->end_time) {
            return 'Todo o dia';
        }

        return "{$start} - {$end}";
    }

    public function getFormattedDateRange(): string
    {
        $start = $this->start_date ? $this->start_date->format('d/m/Y') : 'Sem data inicial';
        $end = $this->end_date ? $this->end_date->format('d/m/Y') : 'Sem data final';

        if (!$this->start_date && !$this->end_date) {
            return 'Indefinido';
        }

        return "{$start} - {$end}";
    }

    public function getFormattedDaysOfWeek(): string
    {
        if (!$this->days_of_week || empty($this->days_of_week)) {
            return 'Todos os dias';
        }

        $dayNames = [
            0 => 'Dom',
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sáb',
        ];

        $selectedDays = array_map(function ($day) use ($dayNames) {
            return $dayNames[$day] ?? $day;
        }, $this->days_of_week);

        return implode(', ', $selectedDays);
    }

    public function hasConflictWith(PlaylistSchedule $other): bool
    {
        // Check if schedules are for the same tenant
        if ($this->tenant_id !== $other->tenant_id) {
            return false;
        }

        // Check date range overlap
        if ($this->start_date && $other->end_date && $this->start_date > $other->end_date) {
            return false;
        }

        if ($this->end_date && $other->start_date && $this->end_date < $other->start_date) {
            return false;
        }

        // Check time range overlap
        if ($this->start_time && $other->end_time && $this->start_time->format('H:i:s') > $other->end_time->format('H:i:s')) {
            return false;
        }

        if ($this->end_time && $other->start_time && $this->end_time->format('H:i:s') < $other->start_time->format('H:i:s')) {
            return false;
        }

        // Check day of week overlap
        if ($this->days_of_week && $other->days_of_week) {
            $overlap = array_intersect($this->days_of_week, $other->days_of_week);
            if (empty($overlap)) {
                return false;
            }
        }

        return true;
    }
}