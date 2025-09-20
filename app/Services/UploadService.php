<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

class UploadService
{
    private array $allowedMimeTypes = [
        'video' => [
            'video/mp4',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/flv',
            'video/webm',
            'video/mkv',
            'video/quicktime',
        ],
        'image' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
        ],
        'audio' => [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/aac',
            'audio/flac',
        ],
    ];

    private array $storageLimits = [
        'basic' => 1024 * 1024 * 1024, // 1GB
        'professional' => 5 * 1024 * 1024 * 1024, // 5GB
        'enterprise' => 20 * 1024 * 1024 * 1024, // 20GB
    ];

    public function uploadFile(UploadedFile $file, Tenant $tenant, string $type = 'media'): array
    {
        $this->validateFile($file, $tenant);

        $mimeType = $file->getMimeType();
        $fileType = $this->getFileType($mimeType);

        $filename = $this->generateFileName($file);
        $path = $this->generatePath($tenant->id, $fileType, $filename);

        $disk = $this->getStorageDisk();

        $uploadedPath = $file->storeAs(
            dirname($path),
            basename($path),
            ['disk' => $disk]
        );

        $fileData = [
            'path' => $uploadedPath,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'type' => $fileType,
            'url' => $this->generatePublicUrl($uploadedPath),
        ];

        if ($fileType === 'video') {
            $fileData['thumbnail_path'] = $this->generateVideoThumbnail($uploadedPath, $tenant->id);
            $fileData['duration'] = $this->getVideoDuration($uploadedPath);
        } elseif ($fileType === 'image') {
            $fileData['thumbnail_path'] = $this->generateImageThumbnail($uploadedPath, $tenant->id);
        }

        return $fileData;
    }

    public function validateFile(UploadedFile $file, Tenant $tenant): void
    {
        $mimeType = $file->getMimeType();

        if (!$this->isAllowedMimeType($mimeType)) {
            throw new \InvalidArgumentException(
                'Tipo de arquivo não permitido. Tipos aceitos: ' .
                implode(', ', array_merge(...array_values($this->allowedMimeTypes)))
            );
        }

        $maxFileSize = $this->getMaxFileSize($tenant);
        if ($file->getSize() > $maxFileSize) {
            throw new \InvalidArgumentException(
                'Arquivo muito grande. Tamanho máximo permitido: ' .
                $this->formatBytes($maxFileSize)
            );
        }

        if (!$this->checkStorageQuota($tenant, $file->getSize())) {
            throw new \InvalidArgumentException(
                'Quota de armazenamento excedida. Upgrade seu plano ou remova arquivos desnecessários.'
            );
        }
    }

    public function checkStorageQuota(Tenant $tenant, int $fileSize): bool
    {
        $currentUsage = MediaFile::where('tenant_id', $tenant->id)->sum('size');
        $storageLimit = $this->getStorageLimit($tenant);

        return ($currentUsage + $fileSize) <= $storageLimit;
    }

    public function deleteFile(string $path): bool
    {
        $disk = $this->getStorageDisk();

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);

            $thumbnailPath = $this->getThumbnailPath($path);
            if ($thumbnailPath && Storage::disk($disk)->exists($thumbnailPath)) {
                Storage::disk($disk)->delete($thumbnailPath);
            }

            return true;
        }

        return false;
    }

    public function moveFile(string $oldPath, string $newPath): bool
    {
        $disk = $this->getStorageDisk();

        if (!Storage::disk($disk)->exists($oldPath)) {
            return false;
        }

        return Storage::disk($disk)->move($oldPath, $newPath);
    }

    public function getFileUrl(string $path): string
    {
        return $this->generatePublicUrl($path);
    }

    private function isAllowedMimeType(string $mimeType): bool
    {
        foreach ($this->allowedMimeTypes as $types) {
            if (in_array($mimeType, $types)) {
                return true;
            }
        }
        return false;
    }

    private function getFileType(string $mimeType): string
    {
        foreach ($this->allowedMimeTypes as $type => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes)) {
                return $type;
            }
        }
        return 'other';
    }

    private function generateFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = Str::slug($name);

        return $name . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    private function generatePath(int $tenantId, string $type, string $filename): string
    {
        $year = date('Y');
        $month = date('m');

        return "tenants/{$tenantId}/{$type}/{$year}/{$month}/{$filename}";
    }

    private function generatePublicUrl(string $path): string
    {
        $disk = $this->getStorageDisk();

        if ($disk === 'public') {
            return Storage::disk($disk)->url($path);
        }

        return Storage::disk($disk)->temporaryUrl($path, now()->addHours(24));
    }

    private function getStorageDisk(): string
    {
        return config('filesystems.default', 'public');
    }

    private function getMaxFileSize(Tenant $tenant): int
    {
        $plan = $tenant->getSubscriptionPlan();

        return match($plan) {
            'basic' => 100 * 1024 * 1024, // 100MB per file
            'professional' => 500 * 1024 * 1024, // 500MB per file
            'enterprise' => 2 * 1024 * 1024 * 1024, // 2GB per file
            default => 50 * 1024 * 1024, // 50MB default
        };
    }

    private function getStorageLimit(Tenant $tenant): int
    {
        $plan = $tenant->getSubscriptionPlan();

        return $this->storageLimits[$plan] ?? $this->storageLimits['basic'];
    }

    private function generateVideoThumbnail(string $videoPath, int $tenantId): ?string
    {
        try {
            $disk = $this->getStorageDisk();
            $localVideoPath = $this->getLocalPath($videoPath);

            if (!$localVideoPath || !file_exists($localVideoPath)) {
                return null;
            }

            $ffmpeg = FFMpeg::create();
            $video = $ffmpeg->open($localVideoPath);

            $thumbnailName = pathinfo($videoPath, PATHINFO_FILENAME) . '_thumb.jpg';
            $thumbnailPath = "tenants/{$tenantId}/thumbnails/" . date('Y/m') . "/{$thumbnailName}";
            $localThumbnailPath = storage_path('app/temp/' . $thumbnailName);

            $video->frame(TimeCode::fromSeconds(1))
                ->save($localThumbnailPath);

            if (file_exists($localThumbnailPath)) {
                $uploaded = Storage::disk($disk)->putFileAs(
                    dirname($thumbnailPath),
                    new \Illuminate\Http\File($localThumbnailPath),
                    basename($thumbnailPath)
                );

                unlink($localThumbnailPath);

                return $uploaded;
            }

        } catch (\Exception $e) {
            \Log::error('Failed to generate video thumbnail: ' . $e->getMessage());
        }

        return null;
    }

    private function generateImageThumbnail(string $imagePath, int $tenantId): ?string
    {
        try {
            $disk = $this->getStorageDisk();
            $localImagePath = $this->getLocalPath($imagePath);

            if (!$localImagePath || !file_exists($localImagePath)) {
                return null;
            }

            $thumbnailName = pathinfo($imagePath, PATHINFO_FILENAME) . '_thumb.jpg';
            $thumbnailPath = "tenants/{$tenantId}/thumbnails/" . date('Y/m') . "/{$thumbnailName}";
            $localThumbnailPath = storage_path('app/temp/' . $thumbnailName);

            $image = Image::make($localImagePath);
            $image->resize(300, 200, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $image->save($localThumbnailPath, 80);

            if (file_exists($localThumbnailPath)) {
                $uploaded = Storage::disk($disk)->putFileAs(
                    dirname($thumbnailPath),
                    new \Illuminate\Http\File($localThumbnailPath),
                    basename($thumbnailPath)
                );

                unlink($localThumbnailPath);

                return $uploaded;
            }

        } catch (\Exception $e) {
            \Log::error('Failed to generate image thumbnail: ' . $e->getMessage());
        }

        return null;
    }

    private function getVideoDuration(string $videoPath): ?int
    {
        try {
            $localPath = $this->getLocalPath($videoPath);

            if (!$localPath || !file_exists($localPath)) {
                return null;
            }

            $ffprobe = \FFMpeg\FFProbe::create();
            $duration = $ffprobe->format($localPath)->get('duration');

            return (int) round($duration);

        } catch (\Exception $e) {
            \Log::error('Failed to get video duration: ' . $e->getMessage());
            return null;
        }
    }

    private function getLocalPath(string $storagePath): ?string
    {
        $disk = $this->getStorageDisk();

        if ($disk === 'public' || $disk === 'local') {
            return Storage::disk($disk)->path($storagePath);
        }

        try {
            $tempPath = storage_path('app/temp/' . basename($storagePath));
            $content = Storage::disk($disk)->get($storagePath);
            file_put_contents($tempPath, $content);
            return $tempPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getThumbnailPath(string $originalPath): ?string
    {
        $pathInfo = pathinfo($originalPath);
        $thumbnailName = $pathInfo['filename'] . '_thumb.jpg';

        $pathParts = explode('/', $originalPath);
        if (count($pathParts) >= 3) {
            $tenantId = $pathParts[1];
            return "tenants/{$tenantId}/thumbnails/" . date('Y/m') . "/{$thumbnailName}";
        }

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}