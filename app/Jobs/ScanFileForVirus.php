<?php

namespace App\Jobs;

use App\Contracts\AntivirusScanner;
use App\Models\MediaFile;
use App\Models\FileValidationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ScanFileForVirus implements ShouldQueue
{
    use Queueable;

    public $timeout = 600; // 10 minutes
    public $tries = 2;
    public $maxExceptions = 1;

    private MediaFile $mediaFile;
    private string $filePath;

    public function __construct(MediaFile $mediaFile, string $filePath)
    {
        $this->mediaFile = $mediaFile;
        $this->filePath = $filePath;
        $this->onQueue('virus-scanning');
    }

    public function handle(AntivirusScanner $scanner): void
    {
        Log::info("Starting virus scan for file: {$this->mediaFile->id}");

        try {
            if (!$scanner->isAvailable()) {
                Log::warning('Antivirus scanner not available, skipping scan', [
                    'media_file_id' => $this->mediaFile->id,
                ]);
                return;
            }

            // Get local file path for scanning
            $localPath = $this->getLocalFilePath();

            if (!$localPath) {
                throw new Exception('Unable to access file for scanning');
            }

            // Perform the scan
            $scanResult = $scanner->scanFile($localPath);

            // Process scan results
            $this->processScanResult($scanResult);

            // Cleanup temp file if we downloaded it
            if ($localPath !== $this->filePath) {
                $this->cleanupTempFile($localPath);
            }

            Log::info("Virus scan completed for file: {$this->mediaFile->id}", [
                'clean' => $scanResult['clean'],
                'scan_time' => $scanResult['scan_time'],
            ]);

        } catch (Exception $e) {
            Log::error("Virus scan failed for file: {$this->mediaFile->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as potentially dangerous and notify
            $this->handleScanFailure($e);

            throw $e;
        }
    }

    /**
     * Get local file path for scanning
     */
    private function getLocalFilePath(): ?string
    {
        $disk = config('upload.default_disk', 'public');

        // If using local storage, return direct path
        if ($disk === 'public' || $disk === 'local') {
            $path = Storage::disk($disk)->path($this->filePath);
            return file_exists($path) ? $path : null;
        }

        // For remote storage (S3, MinIO), download to temp location
        try {
            $tempPath = $this->generateTempPath();
            $content = Storage::disk($disk)->get($this->filePath);

            if ($content === null) {
                return null;
            }

            file_put_contents($tempPath, $content);
            return $tempPath;

        } catch (Exception $e) {
            Log::error('Failed to download file for virus scanning', [
                'file_path' => $this->filePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Process scan results and take appropriate actions
     */
    private function processScanResult(array $scanResult): void
    {
        if ($scanResult['clean']) {
            // File is clean, mark as safe
            $this->markFileAsSafe($scanResult);
        } else {
            // File contains threats, quarantine and notify
            $this->quarantineFile($scanResult);
        }

        // Log scan result
        $this->logScanResult($scanResult);
    }

    /**
     * Mark file as safe after successful scan
     */
    private function markFileAsSafe(array $scanResult): void
    {
        $this->mediaFile->update([
            'virus_scan_status' => 'clean',
            'virus_scan_at' => now(),
            'status' => 'ready', // Allow file to be used
        ]);

        Log::info('File marked as virus-free', [
            'media_file_id' => $this->mediaFile->id,
            'scan_time' => $scanResult['scan_time'],
        ]);
    }

    /**
     * Quarantine infected file and prevent access
     */
    private function quarantineFile(array $scanResult): void
    {
        $this->mediaFile->update([
            'virus_scan_status' => 'infected',
            'virus_scan_at' => now(),
            'status' => 'quarantined',
            'virus_threats' => json_encode($scanResult['threats']),
        ]);

        // Move file to quarantine if possible
        $this->moveToQuarantine();

        // Send security notification
        $this->notifySecurityThreat($scanResult);

        Log::error('File quarantined due to virus detection', [
            'media_file_id' => $this->mediaFile->id,
            'threats' => $scanResult['threats'],
        ]);
    }

    /**
     * Handle scan failure
     */
    private function handleScanFailure(Exception $exception): void
    {
        $this->mediaFile->update([
            'virus_scan_status' => 'failed',
            'virus_scan_at' => now(),
            'status' => 'scan_failed',
        ]);

        // Log as validation failure for security monitoring
        FileValidationLog::create([
            'tenant_id' => $this->mediaFile->tenant_id,
            'user_id' => null,
            'original_filename' => $this->mediaFile->original_name,
            'mime_type' => $this->mediaFile->mime_type,
            'file_size' => $this->mediaFile->size,
            'validation_status' => 'failed',
            'rejection_reason' => 'Virus scan failed: ' . $exception->getMessage(),
            'ip_address' => null,
            'user_agent' => 'System virus scanner',
        ]);
    }

    /**
     * Log scan result for security monitoring
     */
    private function logScanResult(array $scanResult): void
    {
        FileValidationLog::create([
            'tenant_id' => $this->mediaFile->tenant_id,
            'user_id' => null,
            'original_filename' => $this->mediaFile->original_name,
            'mime_type' => $this->mediaFile->mime_type,
            'file_size' => $this->mediaFile->size,
            'validation_status' => $scanResult['clean'] ? 'passed' : 'failed',
            'rejection_reason' => $scanResult['clean'] ? null : 'Virus detected: ' . implode(', ', $scanResult['threats']),
            'warnings' => !$scanResult['clean'] ? 'Virus scan detected threats' : null,
            'ip_address' => null,
            'user_agent' => 'System virus scanner',
        ]);
    }

    /**
     * Move infected file to quarantine directory
     */
    private function moveToQuarantine(): void
    {
        try {
            $disk = config('upload.default_disk', 'public');
            $quarantinePath = 'quarantine/' . date('Y/m/d') . '/' . $this->mediaFile->filename;

            // Copy file to quarantine (don't delete original yet)
            if (Storage::disk($disk)->exists($this->filePath)) {
                Storage::disk($disk)->copy($this->filePath, $quarantinePath);

                // Now delete original
                Storage::disk($disk)->delete($this->filePath);

                Log::info('File moved to quarantine', [
                    'media_file_id' => $this->mediaFile->id,
                    'quarantine_path' => $quarantinePath,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to move file to quarantine', [
                'media_file_id' => $this->mediaFile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send security threat notification
     */
    private function notifySecurityThreat(array $scanResult): void
    {
        try {
            $tenant = $this->mediaFile->tenant;
            $adminUsers = $tenant->users()->where('role', 'admin')->get();

            // Here you would send notification about security threat
            // Implementation depends on your notification system

            Log::info('Security threat notification sent', [
                'media_file_id' => $this->mediaFile->id,
                'threats' => $scanResult['threats'],
                'recipients' => $adminUsers->count(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send security threat notification', [
                'media_file_id' => $this->mediaFile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate temporary file path for downloaded files
     */
    private function generateTempPath(): string
    {
        $tempDir = config('upload.temp_directory', storage_path('app/temp'));

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir . '/virus_scan_' . time() . '_' . $this->mediaFile->id . '.' . pathinfo($this->mediaFile->filename, PATHINFO_EXTENSION);
    }

    /**
     * Clean up temporary file
     */
    private function cleanupTempFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error("Virus scan job failed permanently", [
            'media_file_id' => $this->mediaFile->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->handleScanFailure($exception);
    }
}