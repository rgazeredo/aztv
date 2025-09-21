<?php

namespace App\Observers;

use App\Models\MediaFile;
use App\Services\ThumbnailService;
use App\Jobs\ThumbnailGenerationJob;
use Illuminate\Support\Facades\Log;

class MediaFileObserver
{
    public function __construct(
        private ThumbnailService $thumbnailService
    ) {}

    /**
     * Handle the MediaFile "created" event.
     */
    public function created(MediaFile $mediaFile): void
    {
        // Dispatch thumbnail generation if auto-generation is enabled
        if (config('thumbnails.processing.generate_on_upload', true)) {
            ThumbnailGenerationJob::dispatch($mediaFile);

            Log::info("Dispatched thumbnail generation for new MediaFile", [
                'media_file_id' => $mediaFile->id,
                'type' => $mediaFile->type,
            ]);
        }
    }

    /**
     * Handle the MediaFile "updated" event.
     */
    public function updated(MediaFile $mediaFile): void
    {
        // If the file path changed, regenerate thumbnails
        if ($mediaFile->isDirty('path') && $mediaFile->getOriginal('path') !== $mediaFile->path) {
            // Delete old thumbnails
            $this->thumbnailService->deleteThumbnails($mediaFile);

            // Generate new thumbnails
            ThumbnailGenerationJob::dispatch($mediaFile);

            Log::info("Regenerating thumbnails due to path change", [
                'media_file_id' => $mediaFile->id,
                'old_path' => $mediaFile->getOriginal('path'),
                'new_path' => $mediaFile->path,
            ]);
        }
    }

    /**
     * Handle the MediaFile "deleted" event.
     */
    public function deleted(MediaFile $mediaFile): void
    {
        // Clean up thumbnails when media file is deleted
        if (config('thumbnails.cleanup.auto_delete', true)) {
            $this->thumbnailService->deleteThumbnails($mediaFile);

            Log::info("Cleaned up thumbnails for deleted MediaFile", [
                'media_file_id' => $mediaFile->id,
            ]);
        }
    }

    /**
     * Handle the MediaFile "restored" event.
     */
    public function restored(MediaFile $mediaFile): void
    {
        // Regenerate thumbnails when media file is restored
        ThumbnailGenerationJob::dispatch($mediaFile);

        Log::info("Regenerating thumbnails for restored MediaFile", [
            'media_file_id' => $mediaFile->id,
        ]);
    }

    /**
     * Handle the MediaFile "force deleted" event.
     */
    public function forceDeleted(MediaFile $mediaFile): void
    {
        // Clean up thumbnails when media file is force deleted
        $this->thumbnailService->deleteThumbnails($mediaFile);

        Log::info("Force cleaned up thumbnails for permanently deleted MediaFile", [
            'media_file_id' => $mediaFile->id,
        ]);
    }
}