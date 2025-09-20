<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_default',
        'loop_enabled',
        'settings',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'loop_enabled' => 'boolean',
        'settings' => 'array',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('order');
    }

    public function mediaFiles(): BelongsToMany
    {
        return $this->belongsToMany(MediaFile::class, 'playlist_items')
            ->withPivot(['order', 'display_time_override'])
            ->withTimestamps()
            ->orderBy('pivot_order');
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'player_playlists')
            ->withPivot(['priority', 'start_date', 'end_date', 'schedule_config'])
            ->withTimestamps()
            ->orderBy('pivot_priority');
    }

    // Methods
    public function getTotalDuration(): int
    {
        return $this->mediaFiles->sum(function ($mediaFile) {
            $displayTime = $mediaFile->pivot->display_time_override ?? $mediaFile->display_time;
            return $displayTime;
        });
    }

    public function getFormattedDuration(): string
    {
        $totalSeconds = $this->getTotalDuration();
        $minutes = floor($totalSeconds / 60);
        $seconds = $totalSeconds % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getItemsCount(): int
    {
        return $this->items()->count();
    }

    public function addMediaFile(MediaFile $mediaFile, ?int $displayTimeOverride = null): PlaylistItem
    {
        $maxOrder = $this->items()->max('order') ?? 0;

        return $this->items()->create([
            'media_file_id' => $mediaFile->id,
            'order' => $maxOrder + 1,
            'display_time_override' => $displayTimeOverride,
        ]);
    }

    public function removeMediaFile(MediaFile $mediaFile): bool
    {
        $item = $this->items()->where('media_file_id', $mediaFile->id)->first();

        if (!$item) {
            return false;
        }

        // Remove the item
        $item->delete();

        // Reorder remaining items
        $this->reorderItems();

        return true;
    }

    public function reorderItems(array $itemIds = null): void
    {
        if ($itemIds) {
            // Reorder based on provided order
            foreach ($itemIds as $index => $itemId) {
                $this->items()->where('id', $itemId)->update(['order' => $index + 1]);
            }
        } else {
            // Auto-reorder to fill gaps
            $items = $this->items()->orderBy('order')->get();
            foreach ($items as $index => $item) {
                $item->update(['order' => $index + 1]);
            }
        }
    }

    public function duplicate(string $newName): self
    {
        $newPlaylist = $this->replicate();
        $newPlaylist->name = $newName;
        $newPlaylist->is_default = false;
        $newPlaylist->save();

        // Copy playlist items
        foreach ($this->items as $item) {
            $newPlaylist->items()->create([
                'media_file_id' => $item->media_file_id,
                'order' => $item->order,
                'display_time_override' => $item->display_time_override,
            ]);
        }

        return $newPlaylist;
    }

    public function assignToPlayer(Player $player, int $priority = 1, ?array $scheduleConfig = null): void
    {
        $this->players()->attach($player->id, [
            'priority' => $priority,
            'schedule_config' => $scheduleConfig,
        ]);
    }

    public function unassignFromPlayer(Player $player): void
    {
        $this->players()->detach($player->id);
    }

    public function markAsDefault(): void
    {
        // Unmark other default playlists for this tenant
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    // Scopes
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeWithItems($query)
    {
        return $query->has('items');
    }

    public function scopeEmpty($query)
    {
        return $query->doesntHave('items');
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        // When deleting a playlist, also delete its items
        static::deleting(function ($playlist) {
            $playlist->items()->delete();
        });

        // Ensure only one default playlist per tenant
        static::creating(function ($playlist) {
            if ($playlist->is_default) {
                static::where('tenant_id', $playlist->tenant_id)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function ($playlist) {
            if ($playlist->is_default && $playlist->isDirty('is_default')) {
                static::where('tenant_id', $playlist->tenant_id)
                    ->where('id', '!=', $playlist->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
