<?php

namespace App\Http\Controllers;

use App\Services\UploadService;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FileUploadController extends Controller
{
    private UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function upload(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|max:' . $this->getMaxFileSizeInKb($request),
            'folder' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:1000',
            'display_time' => 'nullable|integer|min:1|max:300',
        ]);

        $tenant = auth()->user()->tenant;
        $uploadedFiles = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($request->file('files') as $file) {
                try {
                    $fileData = $this->uploadService->uploadFile($file, $tenant);

                    $mediaFile = MediaFile::create([
                        'tenant_id' => $tenant->id,
                        'filename' => $fileData['filename'],
                        'original_name' => $fileData['original_name'],
                        'mime_type' => $fileData['mime_type'],
                        'size' => $fileData['size'],
                        'path' => $fileData['path'],
                        'thumbnail_path' => $fileData['thumbnail_path'] ?? null,
                        'duration' => $fileData['duration'] ?? null,
                        'display_time' => $request->input('display_time') ?? ($fileData['duration'] ?? 10),
                        'folder' => $request->input('folder'),
                        'tags' => $request->input('tags'),
                        'status' => 'active',
                    ]);

                    $uploadedFiles[] = [
                        'id' => $mediaFile->id,
                        'filename' => $mediaFile->filename,
                        'original_name' => $mediaFile->original_name,
                        'mime_type' => $mediaFile->mime_type,
                        'size' => $mediaFile->size,
                        'formatted_size' => $mediaFile->getFormattedSize(),
                        'url' => $mediaFile->getUrl(),
                        'thumbnail_url' => $mediaFile->getThumbnailUrl(),
                        'duration' => $mediaFile->duration,
                        'display_time' => $mediaFile->display_time,
                        'type' => $fileData['type'],
                    ];

                } catch (\InvalidArgumentException $e) {
                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'error' => 'Erro interno durante o upload: ' . $e->getMessage(),
                    ];
                }
            }

            if (empty($uploadedFiles) && !empty($errors)) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum arquivo foi enviado com sucesso',
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => count($uploadedFiles) . ' arquivo(s) enviado(s) com sucesso',
                'files' => $uploadedFiles,
            ];

            if (!empty($errors)) {
                $response['warnings'] = $errors;
                $response['message'] .= '. Alguns arquivos falharam.';
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Erro durante o upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function uploadSingle(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:' . $this->getMaxFileSizeInKb($request),
            'folder' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:1000',
            'display_time' => 'nullable|integer|min:1|max:300',
        ]);

        $tenant = auth()->user()->tenant;

        try {
            $fileData = $this->uploadService->uploadFile($request->file('file'), $tenant);

            $mediaFile = MediaFile::create([
                'tenant_id' => $tenant->id,
                'filename' => $fileData['filename'],
                'original_name' => $fileData['original_name'],
                'mime_type' => $fileData['mime_type'],
                'size' => $fileData['size'],
                'path' => $fileData['path'],
                'thumbnail_path' => $fileData['thumbnail_path'] ?? null,
                'duration' => $fileData['duration'] ?? null,
                'display_time' => $request->input('display_time') ?? ($fileData['duration'] ?? 10),
                'folder' => $request->input('folder'),
                'tags' => $request->input('tags'),
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Arquivo enviado com sucesso',
                'file' => [
                    'id' => $mediaFile->id,
                    'filename' => $mediaFile->filename,
                    'original_name' => $mediaFile->original_name,
                    'mime_type' => $mediaFile->mime_type,
                    'size' => $mediaFile->size,
                    'formatted_size' => $mediaFile->getFormattedSize(),
                    'url' => $mediaFile->getUrl(),
                    'thumbnail_url' => $mediaFile->getThumbnailUrl(),
                    'duration' => $mediaFile->duration,
                    'display_time' => $mediaFile->display_time,
                    'type' => $fileData['type'],
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro durante o upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getUploadInfo(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $currentUsage = MediaFile::where('tenant_id', $tenant->id)->sum('size');
        $storageLimit = $this->getStorageLimit($tenant);
        $maxFileSize = $this->getMaxFileSize($tenant);

        return response()->json([
            'storage' => [
                'used' => $currentUsage,
                'limit' => $storageLimit,
                'available' => $storageLimit - $currentUsage,
                'percentage' => round(($currentUsage / $storageLimit) * 100, 2),
                'formatted' => [
                    'used' => $this->formatBytes($currentUsage),
                    'limit' => $this->formatBytes($storageLimit),
                    'available' => $this->formatBytes($storageLimit - $currentUsage),
                ],
            ],
            'limits' => [
                'max_file_size' => $maxFileSize,
                'max_files_per_upload' => 10,
                'allowed_types' => [
                    'video' => ['mp4', 'avi', 'mov', 'wmv', 'webm', 'mkv'],
                    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
                    'audio' => ['mp3', 'wav', 'ogg', 'aac', 'flac'],
                ],
                'formatted' => [
                    'max_file_size' => $this->formatBytes($maxFileSize),
                ],
            ],
            'plan' => [
                'name' => ucfirst($tenant->getSubscriptionPlan()),
                'features' => $this->getPlanFeatures($tenant->getSubscriptionPlan()),
            ],
        ]);
    }

    public function validateUpload(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'size' => 'required|integer|min:1',
            'type' => 'required|string',
        ]);

        $tenant = auth()->user()->tenant;

        try {
            $this->uploadService->validateFile(
                new \Illuminate\Http\Testing\File($request->input('filename'), tmpfile()),
                $tenant
            );

            return response()->json([
                'valid' => true,
                'message' => 'Arquivo válido para upload',
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'valid' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function getMaxFileSizeInKb(Request $request): int
    {
        $tenant = auth()->user()->tenant;
        return $this->getMaxFileSize($tenant) / 1024; // Convert to KB for validation
    }

    private function getMaxFileSize($tenant): int
    {
        $plan = $tenant->getSubscriptionPlan();

        return match($plan) {
            'basic' => 100 * 1024 * 1024, // 100MB
            'professional' => 500 * 1024 * 1024, // 500MB
            'enterprise' => 2 * 1024 * 1024 * 1024, // 2GB
            default => 50 * 1024 * 1024, // 50MB
        };
    }

    private function getStorageLimit($tenant): int
    {
        $plan = $tenant->getSubscriptionPlan();

        return match($plan) {
            'basic' => 1024 * 1024 * 1024, // 1GB
            'professional' => 5 * 1024 * 1024 * 1024, // 5GB
            'enterprise' => 20 * 1024 * 1024 * 1024, // 20GB
            default => 1024 * 1024 * 1024, // 1GB
        };
    }

    private function getPlanFeatures(string $plan): array
    {
        return match($plan) {
            'basic' => [
                'storage' => '1GB',
                'max_file_size' => '100MB',
                'players' => 'Até 5',
                'support' => 'Básico',
            ],
            'professional' => [
                'storage' => '5GB',
                'max_file_size' => '500MB',
                'players' => 'Até 20',
                'support' => 'Prioritário',
            ],
            'enterprise' => [
                'storage' => '20GB',
                'max_file_size' => '2GB',
                'players' => 'Ilimitados',
                'support' => 'Dedicado',
            ],
            default => [
                'storage' => '1GB',
                'max_file_size' => '50MB',
                'players' => 'Até 3',
                'support' => 'Básico',
            ],
        };
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