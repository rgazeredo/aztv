<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CleanupOrphanedThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'thumbnails:cleanup
                           {--dry-run : Show what would be deleted without actually deleting}
                           {--tenant= : Limit cleanup to specific tenant}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up orphaned thumbnails that no longer have corresponding media files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantFilter = $this->option('tenant');

        $this->info('Starting orphaned thumbnails cleanup...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
        }

        $disk = Storage::disk('public');
        $thumbnailsPath = config('thumbnails.storage.directory', 'thumbnails');

        if (!$disk->exists($thumbnailsPath)) {
            $this->info('No thumbnails directory found. Nothing to clean up.');
            return Command::SUCCESS;
        }

        $totalCleaned = 0;
        $totalSize = 0;

        // Get all tenant directories
        $tenantDirs = $disk->directories($thumbnailsPath);

        foreach ($tenantDirs as $tenantDir) {
            $tenantId = basename($tenantDir);

            // Skip if filtering by specific tenant
            if ($tenantFilter && $tenantFilter !== $tenantId) {
                continue;
            }

            $this->info("Processing tenant: {$tenantId}");

            $cleaned = $this->cleanupTenantThumbnails($disk, $tenantDir, $dryRun);
            $totalCleaned += $cleaned['count'];
            $totalSize += $cleaned['size'];
        }

        $sizeFormatted = $this->formatBytes($totalSize);

        if ($dryRun) {
            $this->info("Would clean up {$totalCleaned} orphaned thumbnail directories ({$sizeFormatted})");
        } else {
            $this->info("Cleaned up {$totalCleaned} orphaned thumbnail directories ({$sizeFormatted})");
        }

        return Command::SUCCESS;
    }

    /**
     * Clean up thumbnails for a specific tenant
     */
    private function cleanupTenantThumbnails($disk, string $tenantDir, bool $dryRun): array
    {
        $tenantId = basename($tenantDir);
        $cleaned = ['count' => 0, 'size' => 0];

        // Get all media file directories in this tenant's thumbnails
        $mediaFileDirs = $disk->directories($tenantDir);

        foreach ($mediaFileDirs as $mediaFileDir) {
            $mediaFileId = basename($mediaFileDir);

            // Check if the media file still exists
            $mediaFileExists = MediaFile::where('id', $mediaFileId)
                ->whereHas('tenant', function ($query) use ($tenantId) {
                    $query->where('id', $tenantId);
                })
                ->exists();

            if (!$mediaFileExists) {
                // This is an orphaned thumbnail directory
                $size = $this->getDirectorySize($disk, $mediaFileDir);

                if ($dryRun) {
                    $this->line("  Would delete: {$mediaFileDir} ({$this->formatBytes($size)})");
                } else {
                    $this->line("  Deleting: {$mediaFileDir} ({$this->formatBytes($size)})");
                    $disk->deleteDirectory($mediaFileDir);

                    Log::info("Deleted orphaned thumbnail directory", [
                        'tenant_id' => $tenantId,
                        'media_file_id' => $mediaFileId,
                        'directory' => $mediaFileDir,
                        'size' => $size,
                    ]);
                }

                $cleaned['count']++;
                $cleaned['size'] += $size;
            }
        }

        // If tenant directory is empty, remove it too
        if (!$dryRun && empty($disk->directories($tenantDir)) && empty($disk->files($tenantDir))) {
            $disk->deleteDirectory($tenantDir);
            $this->line("  Removed empty tenant directory: {$tenantDir}");
        }

        return $cleaned;
    }

    /**
     * Get the total size of a directory
     */
    private function getDirectorySize($disk, string $directory): int
    {
        $size = 0;
        $files = $disk->allFiles($directory);

        foreach ($files as $file) {
            $size += $disk->size($file);
        }

        return $size;
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}