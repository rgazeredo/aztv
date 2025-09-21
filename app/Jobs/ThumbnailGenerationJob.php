<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Services\ThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ThumbnailGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public int $backoff = 60; // 1 minute

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MediaFile $mediaFile,
        public array $options = []
    ) {
        $this->onQueue(config('thumbnails.processing.queue', 'media'));
    }

    /**
     * Execute the job.
     */
    public function handle(ThumbnailService $thumbnailService): void
    {
        try {
            Log::info("Starting thumbnail generation for MediaFile {$this->mediaFile->id}");

            // Check if file still exists
            if (!$this->mediaFile->exists()) {
                Log::warning("MediaFile {$this->mediaFile->id} no longer exists, skipping thumbnail generation");
                return;
            }

            // Check if thumbnails should be generated for this file type
            if (!$this->shouldGenerateThumbnails()) {
                Log::info("Skipping thumbnail generation for unsupported type: {$this->mediaFile->mime_type}");
                return;
            }

            // Generate thumbnails
            $thumbnailPaths = $thumbnailService->generateThumbnail($this->mediaFile, $this->options);

            if (empty($thumbnailPaths)) {
                Log::warning("No thumbnails generated for MediaFile {$this->mediaFile->id}");
                return;
            }

            Log::info("Successfully generated " . count($thumbnailPaths) . " thumbnails for MediaFile {$this->mediaFile->id}");

            // Update media file status
            $this->mediaFile->update([
                'processing_status' => 'completed',
                'processed_at' => now(),
            ]);

        } catch (Exception $e) {
            Log::error("Thumbnail generation failed for MediaFile {$this->mediaFile->id}: " . $e->getMessage());

            // Update processing status on failure
            $this->mediaFile->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            // Re-throw exception to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("Thumbnail generation job failed permanently for MediaFile {$this->mediaFile->id}: " . $exception->getMessage());

        // Update media file with final failure status
        $this->mediaFile->update([
            'processing_status' => 'failed',
            'processing_error' => $exception->getMessage(),
        ]);

        // Optionally send notification about the failure
        if (config('thumbnails.error_handling.notification_channels')) {
            // Implementation for notifications would go here
        }
    }

    /**
     * Determine if thumbnails should be generated for this media file
     */
    private function shouldGenerateThumbnails(): bool
    {
        $supportedTypes = config('thumbnails.supported_types', []);
        $allSupportedTypes = array_merge(
            $supportedTypes['image'] ?? [],
            $supportedTypes['video'] ?? []
        );

        return in_array($this->mediaFile->mime_type, $allSupportedTypes);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'thumbnail-generation',
            'media-file:' . $this->mediaFile->id,
            'tenant:' . tenant('id'),
            'type:' . $this->mediaFile->type,
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 180, 300]; // 1min, 3min, 5min
    }

    /**
     * Determine if the job should be retried based on the exception.
     */
    public function shouldRetry(Exception $exception): bool
    {
        // Don't retry for certain types of exceptions
        $nonRetryableExceptions = [
            'InvalidArgumentException',
            'FileNotFoundException',
        ];

        $exceptionClass = get_class($exception);

        foreach ($nonRetryableExceptions as $nonRetryable) {
            if (strpos($exceptionClass, $nonRetryable) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'thumbnail-generation:' . $this->mediaFile->id;
    }
}