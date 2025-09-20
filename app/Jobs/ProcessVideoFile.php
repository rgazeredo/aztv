<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Notifications\VideoProcessingCompleted;
use App\Notifications\VideoProcessingFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Coordinate\TimeCode;
use Exception;

class ProcessVideoFile implements ShouldQueue
{
    use Queueable;

    public $timeout = 1800; // 30 minutes
    public $tries = 3;
    public $maxExceptions = 1;

    private MediaFile $mediaFile;

    public function __construct(MediaFile $mediaFile)
    {
        $this->mediaFile = $mediaFile;
        $this->onQueue('video-processing');
    }

    public function handle(): void
    {
        Log::info("Starting video processing for file: {$this->mediaFile->id}");

        try {
            $this->updateStatus('processing');

            $processedData = $this->processVideo();

            $this->updateMediaFile($processedData);

            $this->updateStatus('completed');

            $this->notifyCompletion();

            Log::info("Video processing completed for file: {$this->mediaFile->id}");

        } catch (Exception $e) {
            Log::error("Video processing failed for file: {$this->mediaFile->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateStatus('failed');
            $this->notifyFailure($e);

            throw $e;
        }
    }

    private function processVideo(): array
    {
        $disk = config('upload.default_disk', 'public');
        $originalPath = $this->mediaFile->path;

        $localInputPath = $this->getLocalPath($originalPath);
        $tempOutputPath = $this->generateTempOutputPath();
        $tempThumbnailPath = $this->generateTempThumbnailPath();

        try {
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => config('upload.video_processing.ffmpeg_path', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => config('upload.video_processing.ffprobe_path', '/usr/bin/ffprobe'),
                'timeout' => 1800,
                'ffmpeg.threads' => 4,
            ]);

            $video = $ffmpeg->open($localInputPath);

            $this->compressVideo($video, $tempOutputPath);

            $this->generateThumbnail($video, $tempThumbnailPath);

            $duration = $this->getVideoDuration($localInputPath);

            $compressedPath = $this->uploadCompressedVideo($tempOutputPath);
            $thumbnailPath = $this->uploadThumbnail($tempThumbnailPath);

            $this->cleanupTempFiles([$tempOutputPath, $tempThumbnailPath]);

            if ($localInputPath !== Storage::disk($disk)->path($originalPath)) {
                unlink($localInputPath);
            }

            return [
                'compressed_path' => $compressedPath,
                'thumbnail_path' => $thumbnailPath,
                'duration' => $duration,
                'processed_size' => filesize($tempOutputPath),
            ];

        } catch (Exception $e) {
            $this->cleanupTempFiles([$tempOutputPath, $tempThumbnailPath, $localInputPath]);
            throw $e;
        }
    }

    private function compressVideo($video, string $outputPath): void
    {
        $format = new X264();

        $format->setKiloBitrate(config('media.compression.video_bitrate', 1000))
               ->setAudioKiloBitrate(config('media.compression.audio_bitrate', 128));

        $video->save($format, $outputPath);
    }

    private function generateThumbnail($video, string $thumbnailPath): void
    {
        $thumbnailTime = config('upload.video_processing.thumbnail_time', 1);

        $video->frame(TimeCode::fromSeconds($thumbnailTime))
              ->save($thumbnailPath);
    }

    private function getVideoDuration(string $videoPath): int
    {
        $ffprobe = \FFMpeg\FFProbe::create([
            'ffprobe.binaries' => config('upload.video_processing.ffprobe_path', '/usr/bin/ffprobe'),
        ]);

        return (int) round($ffprobe->format($videoPath)->get('duration'));
    }

    private function getLocalPath(string $storagePath): string
    {
        $disk = config('upload.default_disk', 'public');

        if ($disk === 'public' || $disk === 'local') {
            return Storage::disk($disk)->path($storagePath);
        }

        $tempPath = $this->generateTempPath('input_' . basename($storagePath));
        $content = Storage::disk($disk)->get($storagePath);
        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    private function uploadCompressedVideo(string $localPath): string
    {
        $disk = config('upload.default_disk', 'public');
        $originalPath = $this->mediaFile->path;

        $pathInfo = pathinfo($originalPath);
        $compressedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_compressed.' . $pathInfo['extension'];

        Storage::disk($disk)->putFileAs(
            dirname($compressedPath),
            new \Illuminate\Http\File($localPath),
            basename($compressedPath)
        );

        return $compressedPath;
    }

    private function uploadThumbnail(string $localPath): string
    {
        $disk = config('upload.default_disk', 'public');
        $tenantId = $this->mediaFile->tenant_id;

        $thumbnailName = pathinfo($this->mediaFile->path, PATHINFO_FILENAME) . '_thumb.jpg';
        $thumbnailPath = "tenants/{$tenantId}/thumbnails/" . date('Y/m') . "/{$thumbnailName}";

        Storage::disk($disk)->putFileAs(
            dirname($thumbnailPath),
            new \Illuminate\Http\File($localPath),
            basename($thumbnailPath)
        );

        return $thumbnailPath;
    }

    private function generateTempPath(string $filename): string
    {
        $tempDir = config('upload.temp_directory', storage_path('app/temp'));

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir . '/' . $filename;
    }

    private function generateTempOutputPath(): string
    {
        return $this->generateTempPath('compressed_' . time() . '_' . $this->mediaFile->id . '.mp4');
    }

    private function generateTempThumbnailPath(): string
    {
        return $this->generateTempPath('thumbnail_' . time() . '_' . $this->mediaFile->id . '.jpg');
    }

    private function cleanupTempFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path && file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function updateStatus(string $status): void
    {
        $this->mediaFile->update([
            'processing_status' => $status,
            'processed_at' => $status === 'completed' ? now() : null,
        ]);

        Log::info("Updated video processing status to: {$status}", [
            'media_file_id' => $this->mediaFile->id,
        ]);
    }

    private function updateMediaFile(array $data): void
    {
        $this->mediaFile->update([
            'path' => $data['compressed_path'],
            'thumbnail_path' => $data['thumbnail_path'],
            'duration' => $data['duration'],
            'size' => $data['processed_size'],
            'processing_status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    private function notifyCompletion(): void
    {
        try {
            $tenant = $this->mediaFile->tenant;
            $adminUsers = $tenant->users()->where('role', 'admin')->get();

            Notification::send($adminUsers, new VideoProcessingCompleted($this->mediaFile));

            Log::info("Sent video processing completion notification", [
                'media_file_id' => $this->mediaFile->id,
                'recipients' => $adminUsers->count(),
            ]);

        } catch (Exception $e) {
            Log::warning("Failed to send completion notification", [
                'media_file_id' => $this->mediaFile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyFailure(Exception $exception): void
    {
        try {
            $tenant = $this->mediaFile->tenant;
            $adminUsers = $tenant->users()->where('role', 'admin')->get();

            Notification::send($adminUsers, new VideoProcessingFailed($this->mediaFile, $exception));

            Log::info("Sent video processing failure notification", [
                'media_file_id' => $this->mediaFile->id,
                'recipients' => $adminUsers->count(),
            ]);

        } catch (Exception $e) {
            Log::warning("Failed to send failure notification", [
                'media_file_id' => $this->mediaFile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error("Video processing job failed permanently", [
            'media_file_id' => $this->mediaFile->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->updateStatus('failed');
        $this->notifyFailure($exception);
    }
}
