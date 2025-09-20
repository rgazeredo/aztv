<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'playlist_id',
        'media_file_id',
        'order',
        'display_time_override',
    ];

    protected $casts = [
        'order' => 'integer',
        'display_time_override' => 'integer',
    ];

    // Relationships
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    // Methods
    public function getDisplayTime(): int
    {
        return $this->display_time_override ?? $this->mediaFile->display_time;
    }

    public function moveUp(): bool
    {
        if ($this->order <= 1) {
            return false;
        }

        $previousItem = $this->playlist->items()
            ->where('order', $this->order - 1)
            ->first();

        if (!$previousItem) {
            return false;
        }

        // Swap orders
        $currentOrder = $this->order;
        $this->update(['order' => $previousItem->order]);
        $previousItem->update(['order' => $currentOrder]);

        return true;
    }

    public function moveDown(): bool
    {
        $nextItem = $this->playlist->items()
            ->where('order', $this->order + 1)
            ->first();

        if (!$nextItem) {
            return false;
        }

        // Swap orders
        $currentOrder = $this->order;
        $this->update(['order' => $nextItem->order]);
        $nextItem->update(['order' => $currentOrder]);

        return true;
    }

    public function moveToPosition(int $newPosition): void
    {
        $oldPosition = $this->order;

        if ($newPosition === $oldPosition) {
            return;
        }

        if ($newPosition < $oldPosition) {
            // Moving up - increment orders of items between new and old position
            $this->playlist->items()
                ->where('order', '>=', $newPosition)
                ->where('order', '<', $oldPosition)
                ->increment('order');
        } else {
            // Moving down - decrement orders of items between old and new position
            $this->playlist->items()
                ->where('order', '>', $oldPosition)
                ->where('order', '<=', $newPosition)
                ->decrement('order');
        }

        $this->update(['order' => $newPosition]);
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeForPlaylist($query, $playlistId)
    {
        return $query->where('playlist_id', $playlistId);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        // When creating, ensure unique order within playlist
        static::creating(function ($item) {
            if (!$item->order) {
                $maxOrder = static::where('playlist_id', $item->playlist_id)->max('order') ?? 0;
                $item->order = $maxOrder + 1;
            } else {
                // Check if order already exists and adjust accordingly
                $existingItem = static::where('playlist_id', $item->playlist_id)
                    ->where('order', $item->order)
                    ->first();

                if ($existingItem) {
                    // Increment order of all items at or after this position
                    static::where('playlist_id', $item->playlist_id)
                        ->where('order', '>=', $item->order)
                        ->increment('order');
                }
            }
        });

        // When deleting, reorder remaining items to fill gap
        static::deleted(function ($item) {
            static::where('playlist_id', $item->playlist_id)
                ->where('order', '>', $item->order)
                ->decrement('order');
        });
    }
}
