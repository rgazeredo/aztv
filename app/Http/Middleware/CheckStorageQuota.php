<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\MediaFile;
use Symfony\Component\HttpFoundation\Response;

class CheckStorageQuota
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado ou sem tenant',
                'error_code' => 'UNAUTHORIZED',
            ], 401);
        }

        $tenant = auth()->user()->tenant;

        if (!$this->hasStorageQuota($tenant, $request)) {
            return response()->json([
                'success' => false,
                'message' => 'Quota de armazenamento excedida. Faça upgrade do seu plano ou remova arquivos desnecessários.',
                'error_code' => 'STORAGE_QUOTA_EXCEEDED',
                'quota_info' => $this->getQuotaInfo($tenant),
            ], 422);
        }

        return $next($request);
    }

    private function hasStorageQuota($tenant, Request $request): bool
    {
        $currentUsage = MediaFile::where('tenant_id', $tenant->id)->sum('size');
        $storageLimit = $this->getStorageLimit($tenant);

        if ($currentUsage >= $storageLimit) {
            return false;
        }

        $uploadSize = $this->calculateUploadSize($request);

        if (($currentUsage + $uploadSize) > $storageLimit) {
            return false;
        }

        return true;
    }

    private function calculateUploadSize(Request $request): int
    {
        $totalSize = 0;

        if ($request->hasFile('file')) {
            $totalSize += $request->file('file')->getSize();
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $totalSize += $file->getSize();
            }
        }

        return $totalSize;
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

    private function getQuotaInfo($tenant): array
    {
        $currentUsage = MediaFile::where('tenant_id', $tenant->id)->sum('size');
        $storageLimit = $this->getStorageLimit($tenant);

        return [
            'current_usage' => $currentUsage,
            'storage_limit' => $storageLimit,
            'available' => max(0, $storageLimit - $currentUsage),
            'percentage_used' => round(($currentUsage / $storageLimit) * 100, 2),
            'formatted' => [
                'current_usage' => $this->formatBytes($currentUsage),
                'storage_limit' => $this->formatBytes($storageLimit),
                'available' => $this->formatBytes(max(0, $storageLimit - $currentUsage)),
            ],
            'plan' => [
                'name' => ucfirst($tenant->getSubscriptionPlan()),
                'upgrade_url' => route('subscription.plans'),
            ],
        ];
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