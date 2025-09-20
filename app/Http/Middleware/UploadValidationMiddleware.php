<?php

namespace App\Http\Middleware;

use App\Services\MediaValidationService;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class UploadValidationMiddleware
{
    public function __construct(
        private MediaValidationService $validationService
    ) {}

    /**
     * Handle an incoming request with file upload validation
     */
    public function handle(Request $request, Closure $next, ...$options): Response
    {
        // Only process requests with file uploads
        if (!$request->hasFile('file') && !$request->hasFile('files')) {
            return $next($request);
        }

        // Get current tenant
        $tenant = $this->getCurrentTenant($request);
        if (!$tenant) {
            return $this->errorResponse('Tenant not found', 403);
        }

        // Parse middleware options
        $config = $this->parseOptions($options);

        // Validate single file or multiple files
        if ($request->hasFile('file')) {
            $validationResult = $this->validateSingleFile($request->file('file'), $tenant, $config);
        } else {
            $validationResult = $this->validateMultipleFiles($request->file('files'), $tenant, $config);
        }

        if (!$validationResult['valid']) {
            return $this->errorResponse($validationResult['errors'], 422, $validationResult);
        }

        // Add validation warnings to request attributes for controller access
        if (!empty($validationResult['warnings'])) {
            $request->attributes->set('upload_warnings', $validationResult['warnings']);
        }

        // Add file info to request attributes
        $request->attributes->set('upload_file_info', $validationResult['file_info'] ?? []);

        return $next($request);
    }

    /**
     * Validate a single uploaded file
     */
    private function validateSingleFile($file, Tenant $tenant, array $config): array
    {
        if (!$file || !$file->isValid()) {
            return [
                'valid' => false,
                'errors' => ['Invalid file upload'],
            ];
        }

        return $this->validationService->validateUpload($file, $tenant, $config);
    }

    /**
     * Validate multiple uploaded files
     */
    private function validateMultipleFiles(array $files, Tenant $tenant, array $config): array
    {
        $allValid = true;
        $allErrors = [];
        $allWarnings = [];
        $allFileInfo = [];

        foreach ($files as $index => $file) {
            if (!$file || !$file->isValid()) {
                $allValid = false;
                $allErrors[] = "File {$index}: Invalid file upload";
                continue;
            }

            $result = $this->validationService->validateUpload($file, $tenant, $config);

            if (!$result['valid']) {
                $allValid = false;
                foreach ($result['errors'] as $error) {
                    $allErrors[] = "File {$index} ({$file->getClientOriginalName()}): {$error}";
                }
            }

            if (!empty($result['warnings'])) {
                foreach ($result['warnings'] as $warning) {
                    $allWarnings[] = "File {$index} ({$file->getClientOriginalName()}): {$warning}";
                }
            }

            $allFileInfo[] = $result['file_info'] ?? [];
        }

        return [
            'valid' => $allValid,
            'errors' => $allErrors,
            'warnings' => $allWarnings,
            'file_info' => $allFileInfo,
        ];
    }

    /**
     * Get current tenant from request
     */
    private function getCurrentTenant(Request $request): ?Tenant
    {
        // Try to get tenant from session (web routes)
        if ($request->session()->has('current_tenant_id')) {
            return Tenant::find($request->session()->get('current_tenant_id'));
        }

        // Try to get tenant from authenticated user
        $user = $request->user();
        if ($user && $user->current_tenant_id) {
            return Tenant::find($user->current_tenant_id);
        }

        // Try to get tenant from route parameter
        if ($request->route('tenant')) {
            return Tenant::find($request->route('tenant'));
        }

        // For API routes, try to get from player authentication
        if ($request->header('X-Player-Token')) {
            $player = \App\Models\Player::where('api_token', $request->header('X-Player-Token'))->first();
            return $player?->tenant;
        }

        return null;
    }

    /**
     * Parse middleware options
     */
    private function parseOptions(array $options): array
    {
        $config = [
            'strict_mode' => false,
            'max_files' => 10,
            'allowed_categories' => ['video', 'image', 'audio'],
        ];

        foreach ($options as $option) {
            if ($option === 'strict') {
                $config['strict_mode'] = true;
            } elseif (str_starts_with($option, 'max_files:')) {
                $config['max_files'] = (int) substr($option, 10);
            } elseif (str_starts_with($option, 'category:')) {
                $categories = explode(',', substr($option, 9));
                $config['allowed_categories'] = $categories;
            }
        }

        return $config;
    }

    /**
     * Return error response
     */
    private function errorResponse(string|array $message, int $status = 422, array $extra = []): JsonResponse
    {
        $errors = is_array($message) ? $message : [$message];

        $response = [
            'success' => false,
            'message' => 'File validation failed',
            'errors' => $errors,
        ];

        // Include warnings if available
        if (!empty($extra['warnings'])) {
            $response['warnings'] = $extra['warnings'];
        }

        // Include file info if available
        if (!empty($extra['file_info'])) {
            $response['file_info'] = $extra['file_info'];
        }

        Log::warning('Upload validation failed', [
            'errors' => $errors,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'tenant_id' => $this->getCurrentTenant(request())?->id,
        ]);

        return response()->json($response, $status);
    }
}