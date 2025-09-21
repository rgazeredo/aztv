<?php

namespace App\Services;

use App\Models\MediaFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Exception;

class ThumbnailService
{
    private array $thumbnailSizes = [
        'small' => ['width' => 150, 'height' => 150],
        'medium' => ['width' => 300, 'height' => 300],
        'large' => ['width' => 600, 'height' => 600],
    ];

    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Generate thumbnails for a media file
     */
    public function generateThumbnail(MediaFile $mediaFile, array $options = []): array
    {
        try {
            $fullPath = Storage::disk('public')->path($mediaFile->path);

            if (!file_exists($fullPath)) {
                Log::error("File not found for thumbnail generation: {$fullPath}");
                return [];
            }

            $thumbnailPaths = [];

            switch ($mediaFile->type) {
                case 'image':
                    $thumbnailPaths = $this->generateImageThumbnails($mediaFile, $fullPath, $options);
                    break;
                case 'video':
                    $thumbnailPaths = $this->generateVideoThumbnails($mediaFile, $fullPath, $options);
                    break;
                default:
                    Log::warning("Unsupported media type for thumbnail generation: {$mediaFile->type}");
                    return [];
            }

            // Update media file with thumbnail paths
            if (!empty($thumbnailPaths)) {
                $mediaFile->update([
                    'thumbnail_path' => $thumbnailPaths['medium'] ?? $thumbnailPaths['small'] ?? null,
                    'thumbnails' => $thumbnailPaths,
                ]);
            }

            return $thumbnailPaths;

        } catch (Exception $e) {
            Log::error("Thumbnail generation failed for MediaFile {$mediaFile->id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate thumbnails for image files
     */
    private function generateImageThumbnails(MediaFile $mediaFile, string $sourcePath, array $options): array
    {
        $thumbnailPaths = [];
        $baseDir = $this->getThumbnailDirectory($mediaFile);

        foreach ($this->thumbnailSizes as $size => $dimensions) {
            try {
                $thumbnailPath = "{$baseDir}/{$size}.jpg";
                $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);

                // Ensure directory exists
                $directory = dirname($fullThumbnailPath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Create thumbnail using Intervention Image
                $image = $this->imageManager->read($sourcePath);
                $image->scale(
                    width: $dimensions['width'],
                    height: $dimensions['height']
                );
                $image->toJpeg(90)->save($fullThumbnailPath);

                $thumbnailPaths[$size] = $thumbnailPath;

                Log::info("Generated {$size} thumbnail for MediaFile {$mediaFile->id}");

            } catch (Exception $e) {
                Log::error("Failed to generate {$size} thumbnail for MediaFile {$mediaFile->id}: " . $e->getMessage());
            }
        }

        return $thumbnailPaths;
    }

    /**
     * Generate thumbnails for video files using FFmpeg
     */
    private function generateVideoThumbnails(MediaFile $mediaFile, string $sourcePath, array $options): array
    {
        $thumbnailPaths = [];
        $baseDir = $this->getThumbnailDirectory($mediaFile);
        $timestamp = $options['timestamp'] ?? '00:00:01';

        // Check if FFmpeg is available
        if (!$this->isFFmpegAvailable()) {
            Log::error("FFmpeg not available for video thumbnail generation");
            return [];
        }

        foreach ($this->thumbnailSizes as $size => $dimensions) {
            try {
                $thumbnailPath = "{$baseDir}/{$size}.jpg";
                $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);

                // Ensure directory exists
                $directory = dirname($fullThumbnailPath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Generate thumbnail using FFmpeg
                $command = sprintf(
                    'ffmpeg -i %s -ss %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2" -y %s 2>/dev/null',
                    escapeshellarg($sourcePath),
                    escapeshellarg($timestamp),
                    $dimensions['width'],
                    $dimensions['height'],
                    $dimensions['width'],
                    $dimensions['height'],
                    escapeshellarg($fullThumbnailPath)
                );

                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($fullThumbnailPath)) {
                    $thumbnailPaths[$size] = $thumbnailPath;
                    Log::info("Generated {$size} video thumbnail for MediaFile {$mediaFile->id}");
                } else {
                    Log::error("FFmpeg failed to generate {$size} thumbnail for MediaFile {$mediaFile->id}. Return code: {$returnCode}");
                }

            } catch (Exception $e) {
                Log::error("Failed to generate {$size} video thumbnail for MediaFile {$mediaFile->id}: " . $e->getMessage());
            }
        }

        return $thumbnailPaths;
    }

    /**
     * Get thumbnail directory path for a media file
     */
    private function getThumbnailDirectory(MediaFile $mediaFile): string
    {
        $tenantId = tenant('id');
        return "thumbnails/{$tenantId}/{$mediaFile->id}";
    }

    /**
     * Check if FFmpeg is available
     */
    private function isFFmpegAvailable(): bool
    {
        exec('which ffmpeg', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Delete all thumbnails for a media file
     */
    public function deleteThumbnails(MediaFile $mediaFile): bool
    {
        try {
            $baseDir = $this->getThumbnailDirectory($mediaFile);
            $fullPath = Storage::disk('public')->path($baseDir);

            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
                Log::info("Deleted thumbnails for MediaFile {$mediaFile->id}");
                return true;
            }

            return true;

        } catch (Exception $e) {
            Log::error("Failed to delete thumbnails for MediaFile {$mediaFile->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recursively remove directory and its contents
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);

        foreach ($files as $file) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        rmdir($path);
    }

    /**
     * Get thumbnail URL for a media file
     */
    public function getThumbnailUrl(MediaFile $mediaFile, string $size = 'medium'): ?string
    {
        if (!$mediaFile->thumbnails || !isset($mediaFile->thumbnails[$size])) {
            return null;
        }

        return Storage::disk('public')->url($mediaFile->thumbnails[$size]);
    }

    /**
     * Check if thumbnails exist for a media file
     */
    public function hasThumbnails(MediaFile $mediaFile): bool
    {
        return !empty($mediaFile->thumbnails);
    }

    /**
     * Regenerate thumbnails for a media file
     */
    public function regenerateThumbnails(MediaFile $mediaFile, array $options = []): array
    {
        // Delete existing thumbnails
        $this->deleteThumbnails($mediaFile);

        // Generate new thumbnails
        return $this->generateThumbnail($mediaFile, $options);
    }
}