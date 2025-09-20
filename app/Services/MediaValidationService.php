<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\FileValidationLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Exception;

class MediaValidationService
{
    private array $allowedMimeTypes = [
        'video' => [
            'video/mp4',
            'video/quicktime', // .mov
            'video/x-msvideo', // .avi
            'video/x-matroska', // .mkv
            'video/webm',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/flv',
        ],
        'image' => [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
        ],
        'audio' => [
            'audio/mpeg', // .mp3
            'audio/mp3',
            'audio/wav',
            'audio/wave',
            'audio/x-wav',
            'audio/aac',
            'audio/ogg',
            'audio/flac',
        ],
    ];

    private array $extensionMimeMap = [
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        'webm' => 'video/webm',
        'wmv' => 'video/x-ms-wmv',
        'flv' => 'video/x-flv',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'aac' => 'audio/aac',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac',
    ];

    private array $sizeLimits = [
        'basic' => [
            'per_file' => 100 * 1024 * 1024, // 100MB
            'total' => 1024 * 1024 * 1024, // 1GB
        ],
        'professional' => [
            'per_file' => 500 * 1024 * 1024, // 500MB
            'total' => 5 * 1024 * 1024 * 1024, // 5GB
        ],
        'enterprise' => [
            'per_file' => 2 * 1024 * 1024 * 1024, // 2GB
            'total' => 20 * 1024 * 1024 * 1024, // 20GB
        ],
    ];

    /**
     * Validate uploaded file against security and business rules
     */
    public function validateUpload(UploadedFile $file, Tenant $tenant, array $options = []): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'file_info' => [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
            ],
        ];

        try {
            // 1. Validate MIME type
            $mimeValidation = $this->validateMimeType($file);
            if (!$mimeValidation['valid']) {
                $result['valid'] = false;
                $result['errors'][] = $mimeValidation['error'];
            }

            // 2. Validate file extension vs MIME type
            $extensionValidation = $this->validateExtensionMimeConsistency($file);
            if (!$extensionValidation['valid']) {
                $result['valid'] = false;
                $result['errors'][] = $extensionValidation['error'];
            }

            // 3. Validate file size
            $sizeValidation = $this->validateFileSize($file, $tenant);
            if (!$sizeValidation['valid']) {
                $result['valid'] = false;
                $result['errors'][] = $sizeValidation['error'];
            }

            // 4. Validate file header
            $headerValidation = $this->validateFileHeader($file);
            if (!$headerValidation['valid']) {
                $result['valid'] = false;
                $result['errors'][] = $headerValidation['error'];
            }

            // 5. Scan file content for suspicious patterns
            $contentValidation = $this->scanFileContent($file->getPathname());
            if (!$contentValidation['valid']) {
                $result['valid'] = false;
                $result['errors'][] = $contentValidation['error'];
            }

            if (!empty($contentValidation['warnings'])) {
                $result['warnings'] = array_merge($result['warnings'], $contentValidation['warnings']);
            }

            // Log validation attempt
            $this->logValidationAttempt($file, $tenant, $result, $options);

        } catch (Exception $e) {
            Log::error('Media validation error', [
                'file' => $file->getClientOriginalName(),
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            $result['valid'] = false;
            $result['errors'][] = 'Validation process failed. Please try again.';
        }

        return $result;
    }

    /**
     * Validate MIME type against allowed types
     */
    private function validateMimeType(UploadedFile $file): array
    {
        $mimeType = $file->getMimeType();
        $allowedMimes = array_merge(
            $this->allowedMimeTypes['video'],
            $this->allowedMimeTypes['image'],
            $this->allowedMimeTypes['audio']
        );

        if (!in_array($mimeType, $allowedMimes)) {
            return [
                'valid' => false,
                'error' => "File type '{$mimeType}' is not allowed. Only video, image, and audio files are permitted.",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate extension matches MIME type to detect potential malicious files
     */
    private function validateExtensionMimeConsistency(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        if (!isset($this->extensionMimeMap[$extension])) {
            return [
                'valid' => false,
                'error' => "File extension '{$extension}' is not supported.",
            ];
        }

        $expectedMimes = (array) $this->extensionMimeMap[$extension];

        // Some files may have alternative MIME types
        $alternativeMimes = [
            'video/quicktime' => ['video/mov'],
            'image/jpeg' => ['image/jpg'],
            'audio/wav' => ['audio/wave', 'audio/x-wav'],
        ];

        foreach ($expectedMimes as $expectedMime) {
            if ($mimeType === $expectedMime) {
                return ['valid' => true];
            }

            if (isset($alternativeMimes[$expectedMime]) && in_array($mimeType, $alternativeMimes[$expectedMime])) {
                return ['valid' => true];
            }
        }

        return [
            'valid' => false,
            'error' => "File extension '{$extension}' does not match detected file type '{$mimeType}'. This may indicate a malicious file.",
        ];
    }

    /**
     * Validate file size against tenant limits
     */
    private function validateFileSize(UploadedFile $file, Tenant $tenant): array
    {
        $fileSize = $file->getSize();
        $plan = $tenant->subscription_plan ?? 'basic';

        if (!isset($this->sizeLimits[$plan])) {
            $plan = 'basic'; // fallback
        }

        $maxFileSize = $this->sizeLimits[$plan]['per_file'];

        if ($fileSize > $maxFileSize) {
            $maxSizeMB = round($maxFileSize / (1024 * 1024), 2);
            $fileSizeMB = round($fileSize / (1024 * 1024), 2);

            return [
                'valid' => false,
                'error' => "File size ({$fileSizeMB}MB) exceeds the maximum allowed size ({$maxSizeMB}MB) for your plan.",
            ];
        }

        // Check total storage usage
        $currentUsage = $tenant->getTotalStorageUsage();
        $totalLimit = $this->sizeLimits[$plan]['total'];

        if (($currentUsage + $fileSize) > $totalLimit) {
            $remainingSpace = $totalLimit - $currentUsage;
            $remainingSpaceMB = round($remainingSpace / (1024 * 1024), 2);

            return [
                'valid' => false,
                'error' => "Not enough storage space. You have {$remainingSpaceMB}MB remaining.",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate file header to ensure it matches the expected file type
     */
    private function validateFileHeader(UploadedFile $file): array
    {
        $filePath = $file->getPathname();
        $header = $this->getFileHeader($filePath, 16); // Read first 16 bytes

        if (!$header) {
            return [
                'valid' => false,
                'error' => 'Unable to read file header. File may be corrupted.',
            ];
        }

        $mimeType = $file->getMimeType();
        $expectedSignatures = $this->getFileSignatures();

        foreach ($expectedSignatures as $mime => $signatures) {
            if ($mimeType === $mime) {
                foreach ($signatures as $signature) {
                    if (str_starts_with($header, $signature)) {
                        return ['valid' => true];
                    }
                }
            }
        }

        // If we reach here, the header doesn't match expected signatures
        // This could indicate a disguised file
        return [
            'valid' => false,
            'error' => 'File header does not match the declared file type. This may indicate a malicious file.',
        ];
    }

    /**
     * Scan file content for suspicious patterns
     */
    public function scanFileContent(string $filePath): array
    {
        $result = [
            'valid' => true,
            'warnings' => [],
        ];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [
                'valid' => false,
                'error' => 'File is not accessible for content scanning.',
            ];
        }

        // Read sample of file content
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return [
                'valid' => false,
                'error' => 'Unable to open file for content scanning.',
            ];
        }

        $sampleSize = min(8192, filesize($filePath)); // Read first 8KB or entire file if smaller
        $content = fread($handle, $sampleSize);
        fclose($handle);

        // Scan for suspicious patterns
        $suspiciousPatterns = [
            '/<script[^>]*>/i' => 'JavaScript code detected',
            '/<iframe[^>]*>/i' => 'Iframe element detected',
            '/eval\s*\(/i' => 'Eval function detected',
            '/base64_decode/i' => 'Base64 decode function detected',
            '/exec\s*\(/i' => 'Execution function detected',
            '/system\s*\(/i' => 'System function detected',
            '/shell_exec/i' => 'Shell execution function detected',
        ];

        foreach ($suspiciousPatterns as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                $result['warnings'][] = $description;
            }
        }

        // Check for executable file signatures in media files
        $executableSignatures = [
            "\x4D\x5A" => 'Windows executable',
            "\x7F\x45\x4C\x46" => 'Linux executable',
            "\xFE\xED\xFA" => 'macOS executable',
        ];

        foreach ($executableSignatures as $signature => $description) {
            if (str_contains($content, $signature)) {
                return [
                    'valid' => false,
                    'error' => "Detected {$description} within media file. This is not allowed.",
                ];
            }
        }

        return $result;
    }

    /**
     * Log validation attempt for security monitoring
     */
    private function logValidationAttempt(UploadedFile $file, Tenant $tenant, array $result, array $options): void
    {
        $userId = auth()->id();

        FileValidationLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'validation_status' => $result['valid'] ? 'passed' : 'failed',
            'rejection_reason' => $result['valid'] ? null : implode('; ', $result['errors']),
            'warnings' => !empty($result['warnings']) ? implode('; ', $result['warnings']) : null,
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ]);

        if (!$result['valid']) {
            Log::warning('File upload validation failed', [
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'filename' => $file->getClientOriginalName(),
                'errors' => $result['errors'],
                'ip' => request()->ip(),
            ]);
        }
    }

    /**
     * Get file header bytes
     */
    private function getFileHeader(string $filePath, int $bytes = 16): ?string
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return null;
        }

        $header = fread($handle, $bytes);
        fclose($handle);

        return $header;
    }

    /**
     * Get known file signatures for header validation
     */
    private function getFileSignatures(): array
    {
        return [
            // Video signatures
            'video/mp4' => [
                "\x00\x00\x00\x18\x66\x74\x79\x70", // ftyp
                "\x00\x00\x00\x20\x66\x74\x79\x70", // ftyp
            ],
            'video/quicktime' => [
                "\x00\x00\x00\x14\x66\x74\x79\x70\x71\x74", // QuickTime
            ],
            'video/x-msvideo' => [
                "\x52\x49\x46\x46", // RIFF (AVI)
            ],
            'video/webm' => [
                "\x1A\x45\xDF\xA3", // EBML
            ],

            // Image signatures
            'image/jpeg' => [
                "\xFF\xD8\xFF", // JPEG
            ],
            'image/png' => [
                "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A", // PNG
            ],
            'image/gif' => [
                "\x47\x49\x46\x38\x37\x61", // GIF87a
                "\x47\x49\x46\x38\x39\x61", // GIF89a
            ],
            'image/bmp' => [
                "\x42\x4D", // BM
            ],

            // Audio signatures
            'audio/mpeg' => [
                "\xFF\xFB", // MP3
                "\xFF\xF3", // MP3
                "\xFF\xF2", // MP3
            ],
            'audio/wav' => [
                "\x52\x49\x46\x46", // RIFF
            ],
            'audio/ogg' => [
                "\x4F\x67\x67\x53", // OggS
            ],
        ];
    }

    /**
     * Get allowed MIME types for a specific category
     */
    public function getAllowedMimeTypes(string $category = null): array
    {
        if ($category && isset($this->allowedMimeTypes[$category])) {
            return $this->allowedMimeTypes[$category];
        }

        return array_merge(
            $this->allowedMimeTypes['video'],
            $this->allowedMimeTypes['image'],
            $this->allowedMimeTypes['audio']
        );
    }

    /**
     * Get size limits for a tenant plan
     */
    public function getSizeLimits(string $plan): array
    {
        return $this->sizeLimits[$plan] ?? $this->sizeLimits['basic'];
    }
}