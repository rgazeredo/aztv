<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;

class PlanLimitService
{
    public function canAddPlayer(Tenant $tenant): bool
    {
        $plan = $tenant->getActivePlan();

        if (!$plan) {
            Log::warning('Tenant has no active plan', [
                'tenant_id' => $tenant->id,
            ]);
            return false;
        }

        $currentCount = $tenant->getCurrentPlayerCount();
        $canAdd = $currentCount < $plan->player_limit;

        Log::info('Player limit check', [
            'tenant_id' => $tenant->id,
            'current_players' => $currentCount,
            'player_limit' => $plan->player_limit,
            'can_add' => $canAdd,
        ]);

        return $canAdd;
    }

    public function canUploadFile(Tenant $tenant, int $fileSizeBytes): bool
    {
        $plan = $tenant->getActivePlan();

        if (!$plan) {
            Log::warning('Tenant has no active plan', [
                'tenant_id' => $tenant->id,
            ]);
            return false;
        }

        $currentUsageGb = $tenant->getCurrentStorageUsage();
        $fileSizeGb = $fileSizeBytes / (1024 * 1024 * 1024);
        $totalAfterUpload = $currentUsageGb + $fileSizeGb;
        $canUpload = $totalAfterUpload <= $plan->storage_limit_gb;

        Log::info('Storage limit check', [
            'tenant_id' => $tenant->id,
            'current_usage_gb' => round($currentUsageGb, 2),
            'file_size_gb' => round($fileSizeGb, 2),
            'total_after_upload_gb' => round($totalAfterUpload, 2),
            'storage_limit_gb' => $plan->storage_limit_gb,
            'can_upload' => $canUpload,
        ]);

        return $canUpload;
    }

    public function calculateStorageUsage(Tenant $tenant): float
    {
        $totalBytes = $tenant->mediaFiles()->sum('size') ?: 0;
        $totalGb = $totalBytes / (1024 * 1024 * 1024);

        Log::debug('Storage usage calculated', [
            'tenant_id' => $tenant->id,
            'total_bytes' => $totalBytes,
            'total_gb' => round($totalGb, 2),
        ]);

        return $totalGb;
    }

    public function getRemainingPlayerSlots(Tenant $tenant): int
    {
        $plan = $tenant->getActivePlan();

        if (!$plan) {
            return 0;
        }

        $currentCount = $tenant->getCurrentPlayerCount();
        return max(0, $plan->player_limit - $currentCount);
    }

    public function getRemainingStorageGb(Tenant $tenant): float
    {
        $plan = $tenant->getActivePlan();

        if (!$plan) {
            return 0;
        }

        $currentUsage = $tenant->getCurrentStorageUsage();
        return max(0, $plan->storage_limit_gb - $currentUsage);
    }

    public function getUsagePercentages(Tenant $tenant): array
    {
        $plan = $tenant->getActivePlan();

        if (!$plan) {
            return [
                'player_percentage' => 0,
                'storage_percentage' => 0,
            ];
        }

        $playerPercentage = $plan->player_limit > 0
            ? ($tenant->getCurrentPlayerCount() / $plan->player_limit) * 100
            : 0;

        $storagePercentage = $plan->storage_limit_gb > 0
            ? ($tenant->getCurrentStorageUsage() / $plan->storage_limit_gb) * 100
            : 0;

        return [
            'player_percentage' => round($playerPercentage, 1),
            'storage_percentage' => round($storagePercentage, 1),
        ];
    }

    public function isNearLimit(Tenant $tenant, int $threshold = 80): array
    {
        $percentages = $this->getUsagePercentages($tenant);

        return [
            'near_player_limit' => $percentages['player_percentage'] >= $threshold,
            'near_storage_limit' => $percentages['storage_percentage'] >= $threshold,
        ];
    }

    public function validatePlanChange(Tenant $tenant, Plan $newPlan): array
    {
        $errors = [];

        if ($tenant->getCurrentPlayerCount() > $newPlan->player_limit) {
            $errors['player_limit'] = "Tenant currently has {$tenant->getCurrentPlayerCount()} players, which exceeds the new plan limit of {$newPlan->player_limit}.";
        }

        if ($tenant->getCurrentStorageUsage() > $newPlan->storage_limit_gb) {
            $errors['storage_limit'] = "Tenant currently uses {$tenant->getCurrentStorageUsage()}GB, which exceeds the new plan limit of {$newPlan->storage_limit_gb}GB.";
        }

        return $errors;
    }
}