<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PlanController extends Controller
{
    protected PlanLimitService $planLimitService;

    public function __construct(PlanLimitService $planLimitService)
    {
        $this->planLimitService = $planLimitService;
    }

    public function index()
    {
        $plans = Plan::withCount('tenants')
            ->orderBy('price')
            ->get()
            ->map(function ($plan) {
                $plan->active_tenants_count = $plan->tenants()->active()->count();
                return $plan;
            });

        return view('admin.plans.index', compact('plans'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:plans,name',
            'description' => 'nullable|string',
            'player_limit' => 'required|integer|min:1',
            'storage_limit_gb' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $plan = Plan::create([
                'name' => $request->name,
                'description' => $request->description,
                'player_limit' => $request->player_limit ?? 1,
                'storage_limit_gb' => $request->storage_limit_gb ?? 1,
                'price' => $request->price,
                'is_active' => $request->boolean('is_active', true),
            ]);

            Log::info('Plan created successfully', [
                'plan_id' => $plan->id,
                'name' => $plan->name,
            ]);

            return response()->json([
                'success' => true,
                'plan' => $plan,
                'message' => 'Plan created successfully',
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to create plan', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create plan',
            ], 500);
        }
    }

    public function update(Request $request, Plan $plan)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:plans,name,' . $plan->id,
            'description' => 'nullable|string',
            'player_limit' => 'required|integer|min:1',
            'storage_limit_gb' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenantsWithPlan = $plan->tenants()->active()->get();

            foreach ($tenantsWithPlan as $tenant) {
                if ($tenant->getCurrentPlayerCount() > $request->player_limit) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot reduce player limit. Tenant '{$tenant->name}' currently has {$tenant->getCurrentPlayerCount()} players, which exceeds the new limit of {$request->player_limit}.",
                    ], 422);
                }

                if ($tenant->getCurrentStorageUsage() > $request->storage_limit_gb) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot reduce storage limit. Tenant '{$tenant->name}' currently uses {$tenant->getCurrentStorageUsage()}GB, which exceeds the new limit of {$request->storage_limit_gb}GB.",
                    ], 422);
                }
            }

            $plan->update([
                'name' => $request->name,
                'description' => $request->description,
                'player_limit' => $request->player_limit,
                'storage_limit_gb' => $request->storage_limit_gb,
                'price' => $request->price,
                'is_active' => $request->boolean('is_active'),
            ]);

            Log::info('Plan updated successfully', [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'affected_tenants' => $tenantsWithPlan->count(),
            ]);

            return response()->json([
                'success' => true,
                'plan' => $plan->fresh(),
                'message' => 'Plan updated successfully',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update plan', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update plan',
            ], 500);
        }
    }

    public function applyLimits(Request $request, $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (!$tenant->plan) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant does not have an assigned plan',
            ], 400);
        }

        try {
            $plan = $tenant->plan;

            if ($tenant->getCurrentPlayerCount() > $plan->player_limit) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot apply limits. Tenant currently has {$tenant->getCurrentPlayerCount()} players, which exceeds the plan limit of {$plan->player_limit}.",
                ], 422);
            }

            if ($tenant->getCurrentStorageUsage() > $plan->storage_limit_gb) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot apply limits. Tenant currently uses {$tenant->getCurrentStorageUsage()}GB, which exceeds the plan limit of {$plan->storage_limit_gb}GB.",
                ], 422);
            }

            Log::info('Plan limits applied successfully', [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'player_limit' => $plan->player_limit,
                'storage_limit_gb' => $plan->storage_limit_gb,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plan limits applied successfully',
                'limits' => [
                    'player_limit' => $plan->player_limit,
                    'storage_limit_gb' => $plan->storage_limit_gb,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to apply plan limits', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to apply plan limits',
            ], 500);
        }
    }

    public function checkLimits($tenantId)
    {
        $tenant = Tenant::with('plan')->findOrFail($tenantId);

        $plan = $tenant->getActivePlan();
        $playersUsed = $tenant->getCurrentPlayerCount();
        $storageUsedGb = $tenant->getCurrentStorageUsage();

        $playerLimit = $plan ? $plan->player_limit : 1;
        $storageLimitGb = $plan ? $plan->storage_limit_gb : 1;

        $playerPercentage = $playerLimit > 0 ? ($playersUsed / $playerLimit) * 100 : 0;
        $storagePercentage = $storageLimitGb > 0 ? ($storageUsedGb / $storageLimitGb) * 100 : 0;

        return response()->json([
            'success' => true,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
            ] : null,
            'limits' => [
                'players_used' => $playersUsed,
                'player_limit' => $playerLimit,
                'player_percentage' => round($playerPercentage, 1),
                'storage_used_gb' => round($storageUsedGb, 2),
                'storage_limit_gb' => $storageLimitGb,
                'storage_percentage' => round($storagePercentage, 1),
            ],
            'warnings' => [
                'at_player_limit' => $playersUsed >= $playerLimit,
                'at_storage_limit' => $storageUsedGb >= $storageLimitGb,
                'near_player_limit' => $playerPercentage >= 80,
                'near_storage_limit' => $storagePercentage >= 80,
            ],
        ]);
    }
}
