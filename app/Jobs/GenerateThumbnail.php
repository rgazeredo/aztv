<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Events\ThumbnailGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Exception;

class GenerateThumbnail implements ShouldQueue
{
    use Queueable;

    public $timeout = 600; // 10 minutes
    public $tries = 3;
    public $maxExceptions = 1;

    private MediaFile $mediaFile;

    public function __construct(MediaFile $mediaFile)
    {
        $this->mediaFile = $mediaFile;
        $this->onQueue('thumbnail-generation');
    }

    public function handle(): void
    {
        Log::info("Starting thumbnail generation for file: {$this->mediaFile->id}");

        try {
            $thumbnails = $this->generateThumbnails();

            $this->updateMediaFile($thumbnails);

            event(new ThumbnailGenerated($this->mediaFile, $thumbnails));

            Log::info("Thumbnail generation completed for file: {$this->mediaFile->id}");

        } catch (Exception $e) {
            Log::error("Thumbnail generation failed for file: {$this->mediaFile->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function generateThumbnails(): array
    {
        $disk = config('upload.default_disk', 'public');
        $originalPath = $this->mediaFile->path;

        $localInputPath = $this->getLocalPath($originalPath);
        $thumbnailPaths = [];

        try {
            if ($this->mediaFile->isVideo()) {
                $thumbnailPaths = $this->generateVideoThumbnails($localInputPath);
            } elseif ($this->mediaFile->isImage()) {
                $thumbnailPaths = $this->generateImageThumbnails($localInputPath);
            } else {
                throw new Exception('Unsupported media type for thumbnail generation');
            }

            // Upload thumbnails to storage
            $uploadedPaths = $this->uploadThumbnails($thumbnailPaths);

            // Cleanup temp files
            $this->cleanupTempFiles(array_values($thumbnailPaths));

            if ($localInputPath !== Storage::disk($disk)->path($originalPath)) {
                unlink($localInputPath);
            }

            return $uploadedPaths;

        } catch (Exception $e) {
            $this->cleanupTempFiles(array_values($thumbnailPaths));
            throw $e;
        }
    }

    private function generateVideoThumbnails(string $videoPath): array
    {
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => config('upload.video_processing.ffmpeg_path', '/usr/bin/ffmpeg'),
            'ffprobe.binaries' => config('upload.video_processing.ffprobe_path', '/usr/bin/ffprobe'),
            'timeout' => 300,
            'ffmpeg.threads' => 2,
        ]);

        $video = $ffmpeg->open($videoPath);
        $thumbnailTime = config('upload.video_processing.thumbnail_time', 1);

        $sizes = [
            'small' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 300, 'height' => 300],
            'large' => ['width' => 600, 'height' => 600],
        ];

        $thumbnailPaths = [];

        foreach ($sizes as $size => $dimensions) {
            $tempPath = $this->generateTempThumbnailPath($size);

            $video->frame(TimeCode::fromSeconds($thumbnailTime))
                  ->save($tempPath);

            // Resize using Intervention Image
            $manager = new ImageManager(new Driver());
            $image = $manager->read($tempPath);
            $image->cover($dimensions['width'], $dimensions['height']);
            $image->save($tempPath, quality: 85);

            $thumbnailPaths[$size] = $tempPath;
        }

        return $thumbnailPaths;
    }

    private function generateImageThumbnails(string $imagePath): array
    {
        $manager = new ImageManager(new Driver());
        $originalImage = $manager->read($imagePath);

        $sizes = [
            'small' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 300, 'height' => 300],
            'large' => ['width' => 600, 'height' => 600],
        ];

        $thumbnailPaths = [];

        foreach ($sizes as $size => $dimensions) {
            $tempPath = $this->generateTempThumbnailPath($size);

            $resizedImage = clone $originalImage;
            $resizedImage->cover($dimensions['width'], $dimensions['height']);
            $resizedImage->save($tempPath, quality: 85);

            $thumbnailPaths[$size] = $tempPath;
        }

        return $thumbnailPaths;
    }

    private function uploadThumbnails(array $localPaths): array
    {
        $disk = config('upload.default_disk', 'public');
        $tenantId = $this->mediaFile->tenant_id;
        $uploadedPaths = [];

        foreach ($localPaths as $size => $localPath) {
            $fileName = pathinfo($this->mediaFile->path, PATHINFO_FILENAME) . "_{$size}.jpg";
            $thumbnailPath = "tenants/{$tenantId}/thumbnails/" . date('Y/m') . "/{$fileName}";

            Storage::disk($disk)->putFileAs(
                dirname($thumbnailPath),
                new \Illuminate\Http\File($localPath),
                basename($thumbnailPath)
            );

            $uploadedPaths[$size] = $thumbnailPath;
        }

        return $uploadedPaths;
    }

    private function getLocalPath(string $storagePath): string
    {
        $disk = config('upload.default_disk', 'public');

        if ($disk === 'public' || $disk === 'local') {
            return Storage::disk($disk)->path($storagePath);
        }

        // For remote storage (S3, MinIO), download to temp location
        $tempPath = $this->generateTempPath('input_' . basename($storagePath));
        $content = Storage::disk($disk)->get($storagePath);
        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    private function generateTempPath(string $filename): string
    {
        $tempDir = config('upload.temp_directory', storage_path('app/temp'));

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir . '/' . $filename;
    }

    private function generateTempThumbnailPath(string $size): string
    {
        return $this->generateTempPath("thumbnail_{$size}_" . time() . '_' . $this->mediaFile->id . '.jpg');
    }

    private function cleanupTempFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path && file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function updateMediaFile(array $thumbnails): void
    {
        $updateData = [
            'thumbnails_generated_at' => now(),
        ];

        foreach ($thumbnails as $size => $path) {
            $updateData["thumbnail_{$size}"] = $path;
        }

        $this->mediaFile->update($updateData);

        Log::info("Updated media file with thumbnail paths", [
            'media_file_id' => $this->mediaFile->id,
            'thumbnails' => $thumbnails,
        ]);
    }

    public function failed(Exception $exception): void
    {
        Log::error("Thumbnail generation job failed permanently", [
            'media_file_id' => $this->mediaFile->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // You could create a notification for failed thumbnail generation here
    }
}
