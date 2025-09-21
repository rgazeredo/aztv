<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'filename',
        'original_name',
        'mime_type',
        'size',
        'path',
        'thumbnail_path',
        'thumbnails',
        'duration',
        'display_time',
        'folder',
        'tags',
        'status',
        'processing_status',
        'processing_error',
        'processed_at',
        'type',
    ];

    protected $casts = [
        'tags' => 'array',
        'thumbnails' => 'array',
        'size' => 'integer',
        'duration' => 'integer',
        'display_time' => 'integer',
        'processed_at' => 'datetime',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_items')
            ->withPivot(['order', 'display_time_override'])
            ->withTimestamps()
            ->orderBy('pivot_order');
    }

    // Methods
    public function getUrl(): string
    {
        return Storage::url($this->path);
    }

    public function getThumbnailUrl(?string $size = null): ?string
    {
        $size = $size ?: config('thumbnails.default_size', 'medium');

        // Try to get from new thumbnails array first
        if ($this->thumbnails && isset($this->thumbnails[$size])) {
            return Storage::url($this->thumbnails[$size]);
        }

        // Fallback to legacy thumbnail_path
        if (!$this->thumbnail_path) {
            return null;
        }

        return Storage::url($this->thumbnail_path);
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    public function isDocument(): bool
    {
        return in_array($this->mime_type, ['text/html', 'application/pdf']);
    }

    public function getType(): string
    {
        if ($this->type) {
            return $this->type;
        }

        // Determine type from MIME type
        if ($this->isImage()) return 'image';
        if ($this->isVideo()) return 'video';
        if ($this->isAudio()) return 'audio';
        if ($this->isDocument()) return 'document';

        return 'unknown';
    }

    public function hasThumbnails(): bool
    {
        return !empty($this->thumbnails) || !empty($this->thumbnail_path);
    }

    public function getThumbnailSizes(): array
    {
        return $this->thumbnails ? array_keys($this->thumbnails) : [];
    }

    public function getAllThumbnailUrls(): array
    {
        if (!$this->thumbnails) {
            return [];
        }

        $urls = [];
        foreach ($this->thumbnails as $size => $path) {
            $urls[$size] = Storage::url($path);
        }

        return $urls;
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    public function isVideoProcessing(): bool
    {
        return $this->processing_status === 'processing';
    }

    public function isVideoProcessed(): bool
    {
        return $this->processing_status === 'completed';
    }

    public function videoProcessingFailed(): bool
    {
        return $this->processing_status === 'failed';
    }

    public function getProcessingStatusLabel(): string
    {
        return match($this->processing_status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => 'Unknown',
        };
    }

    public function getFormattedSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFormattedDuration(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function markAsReady(): void
    {
        $this->update(['status' => 'ready']);
    }

    public function markAsError(): void
    {
        $this->update(['status' => 'error']);
    }

    // Static methods for file upload
    public static function createFromUpload(UploadedFile $file, int $tenantId, ?string $folder = null, array $tags = []): self
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("media/{$tenantId}", $filename, 'public');

        return static::create([
            'tenant_id' => $tenantId,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
            'folder' => $folder,
            'tags' => $tags,
            'status' => 'processing',
            'processing_status' => 'pending',
        ]);
    }

    // Validation methods
    public static function isValidMimeType(string $mimeType): bool
    {
        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'video/webm',
            'video/avi',
            'video/mov',
            'text/html',
            'application/pdf',
        ];

        return in_array($mimeType, $allowedTypes);
    }

    // Scopes
    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeError($query)
    {
        return $query->where('status', 'error');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeInFolder($query, $folder)
    {
        return $query->where('folder', $folder);
    }

    public function scopeOfType($query, $type)
    {
        return match($type) {
            'image' => $query->where('mime_type', 'like', 'image/%'),
            'video' => $query->where('mime_type', 'like', 'video/%'),
            'document' => $query->whereIn('mime_type', ['text/html', 'application/pdf']),
            default => $query,
        };
    }

    public function scopeWithTags($query, array $tags)
    {
        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }
        return $query;
    }

    // Delete file when model is deleted
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($mediaFile) {
            if ($mediaFile->path) {
                Storage::disk('public')->delete($mediaFile->path);
            }
            if ($mediaFile->thumbnail_path) {
                Storage::disk('public')->delete($mediaFile->thumbnail_path);
            }
        });
    }
}
