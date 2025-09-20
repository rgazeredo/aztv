<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ApkVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'filename',
        'path',
        'download_count',
        'is_active',
        'changelog',
    ];

    protected $casts = [
        'download_count' => 'integer',
        'is_active' => 'boolean',
    ];

    // Methods
    public function getDownloadUrl(): string
    {
        return Storage::url($this->path);
    }

    public function getFileSize(): int
    {
        return Storage::size($this->path);
    }

    public function getFormattedFileSize(): string
    {
        $bytes = $this->getFileSize();
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    public function activate(): void
    {
        // Deactivate all other versions
        static::query()->update(['is_active' => false]);

        // Activate this version
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function generateShortUrl(): string
    {
        // Generate a short URL for APK download
        $shortCode = Str::random(8);

        // In a real implementation, you would store this in a separate table
        // For now, we'll return a placeholder URL
        return url("/apk/{$shortCode}");
    }

    public function generateQrCode(): string
    {
        // Generate QR code for the download URL
        $downloadUrl = $this->getDownloadUrl();

        // In a real implementation, you would use a QR code library
        // For now, we'll return a placeholder URL
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($downloadUrl);
    }

    // Static methods
    public static function createFromUpload(UploadedFile $file, string $version, ?string $changelog = null): self
    {
        $filename = "aztv-player-{$version}.apk";
        $path = $file->storeAs('apk', $filename, 'public');

        return static::create([
            'version' => $version,
            'filename' => $filename,
            'path' => $path,
            'changelog' => $changelog,
            'is_active' => false,
        ]);
    }

    public static function getLatestVersion(): ?self
    {
        return static::where('is_active', true)->first()
            ?? static::orderBy('created_at', 'desc')->first();
    }

    public static function getActiveVersion(): ?self
    {
        return static::where('is_active', true)->first();
    }

    public static function isVersionExists(string $version): bool
    {
        return static::where('version', $version)->exists();
    }

    // Validation
    public static function isValidApk(UploadedFile $file): bool
    {
        return $file->getClientOriginalExtension() === 'apk'
            && $file->getMimeType() === 'application/vnd.android.package-archive';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeMostDownloaded($query)
    {
        return $query->orderBy('download_count', 'desc');
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        // Delete file when model is deleted
        static::deleting(function ($apkVersion) {
            if ($apkVersion->path) {
                Storage::disk('public')->delete($apkVersion->path);
            }
        });

        // Ensure only one active version
        static::creating(function ($apkVersion) {
            if ($apkVersion->is_active) {
                static::query()->update(['is_active' => false]);
            }
        });

        static::updating(function ($apkVersion) {
            if ($apkVersion->is_active && $apkVersion->isDirty('is_active')) {
                static::where('id', '!=', $apkVersion->id)
                    ->update(['is_active' => false]);
            }
        });
    }
}
