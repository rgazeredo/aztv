<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $query = ActivityLog::forTenant($tenantId)
            ->with(['user', 'subject']);

        // Filtros
        if ($userId = $request->get('user_id')) {
            $query->forUser($userId);
        }

        if ($action = $request->get('action')) {
            $query->byAction($action);
        }

        if ($modelType = $request->get('model_type')) {
            $query->forModel($modelType);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        // Filtro por período
        if ($startDate = $request->get('start_date')) {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }

        if ($endDate = $request->get('end_date')) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        // Período padrão: últimos 30 dias se nenhum filtro de data for especificado
        if (!$startDate && !$endDate) {
            $query->recent(30);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 25))
            ->withQueryString();

        // Transformar dados para o frontend
        $logsData = $logs->getCollection()->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => $log->getActionLabel(),
                'model_name' => $log->getModelName(),
                'description' => $log->description,
                'user_name' => $log->getUserName(),
                'ip_address' => $log->ip_address,
                'has_changes' => $log->hasChanges(),
                'changed_fields' => $log->getChangedFields(),
                'created_at' => $log->created_at,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
            ];
        });

        // Obter dados para filtros
        $users = User::where('tenant_id', $tenantId)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $actions = ActivityLog::forTenant($tenantId)
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();

        $modelTypes = ActivityLog::forTenant($tenantId)
            ->whereNotNull('model_type')
            ->distinct()
            ->pluck('model_type')
            ->map(function ($type) {
                return [
                    'value' => $type,
                    'label' => class_basename($type),
                ];
            })
            ->sortBy('label')
            ->values();

        return Inertia::render('ActivityLogs/Index', [
            'logs' => [
                'data' => $logsData,
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => $request->only([
                'user_id', 'action', 'model_type', 'search',
                'start_date', 'end_date', 'per_page'
            ]),
            'users' => $users,
            'actions' => $actions,
            'model_types' => $modelTypes,
            'stats' => $this->getStats($tenantId),
        ]);
    }

    public function show(ActivityLog $activityLog): Response
    {
        $this->authorize('view', $activityLog);

        $activityLog->load(['user', 'subject']);

        $relatedLogs = null;
        if ($activityLog->model_type && $activityLog->model_id) {
            $relatedLogs = ActivityLog::forModel($activityLog->model_type, $activityLog->model_id)
                ->with(['user'])
                ->where('id', '!=', $activityLog->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'action_label' => $log->getActionLabel(),
                        'description' => $log->description,
                        'user_name' => $log->getUserName(),
                        'created_at' => $log->created_at,
                    ];
                });
        }

        return Inertia::render('ActivityLogs/Show', [
            'log' => [
                'id' => $activityLog->id,
                'action' => $activityLog->action,
                'action_label' => $activityLog->getActionLabel(),
                'model_name' => $activityLog->getModelName(),
                'model_type' => $activityLog->model_type,
                'model_id' => $activityLog->model_id,
                'description' => $activityLog->description,
                'old_values' => $activityLog->old_values,
                'new_values' => $activityLog->new_values,
                'ip_address' => $activityLog->ip_address,
                'user_agent' => $activityLog->user_agent,
                'has_changes' => $activityLog->hasChanges(),
                'changed_fields' => $activityLog->getChangedFields(),
                'created_at' => $activityLog->created_at,
                'user' => $activityLog->user ? [
                    'id' => $activityLog->user->id,
                    'name' => $activityLog->user->name,
                    'email' => $activityLog->user->email,
                ] : null,
                'field_changes' => collect($activityLog->getChangedFields())
                    ->mapWithKeys(function ($field) use ($activityLog) {
                        return [$field => $activityLog->getFieldChange($field)];
                    }),
            ],
            'related_logs' => $relatedLogs,
        ]);
    }

    public function api(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $query = ActivityLog::forTenant($tenantId)
            ->with(['user', 'subject']);

        // Aplicar os mesmos filtros da interface web
        if ($userId = $request->get('user_id')) {
            $query->forUser($userId);
        }

        if ($action = $request->get('action')) {
            $query->byAction($action);
        }

        if ($modelType = $request->get('model_type')) {
            $query->forModel($modelType);
        }

        if ($days = $request->get('days')) {
            $query->recent($days);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 50))
            ->get();

        return response()->json([
            'data' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'action_label' => $log->getActionLabel(),
                    'model_name' => $log->getModelName(),
                    'description' => $log->description,
                    'user_name' => $log->getUserName(),
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at,
                ];
            }),
            'meta' => [
                'total' => $logs->count(),
                'generated_at' => now(),
            ],
        ]);
    }

    protected function getStats(int $tenantId): array
    {
        $recentLogs = ActivityLog::forTenant($tenantId)->recent(7);

        return [
            'total_logs_7d' => $recentLogs->count(),
            'unique_users_7d' => $recentLogs->distinct('user_id')->count('user_id'),
            'most_active_user' => $this->getMostActiveUser($tenantId),
            'most_common_action' => $this->getMostCommonAction($tenantId),
        ];
    }

    protected function getMostActiveUser(int $tenantId): ?array
    {
        $mostActiveUserId = ActivityLog::forTenant($tenantId)
            ->recent(7)
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as activity_count')
            ->orderBy('activity_count', 'desc')
            ->first();

        if (!$mostActiveUserId || !$mostActiveUserId->user_id) {
            return null;
        }

        $user = User::find($mostActiveUserId->user_id);
        if (!$user) {
            return null;
        }

        return [
            'name' => $user->name,
            'activity_count' => $mostActiveUserId->activity_count,
        ];
    }

    protected function getMostCommonAction(int $tenantId): ?array
    {
        $mostCommonAction = ActivityLog::forTenant($tenantId)
            ->recent(7)
            ->groupBy('action')
            ->selectRaw('action, COUNT(*) as action_count')
            ->orderBy('action_count', 'desc')
            ->first();

        if (!$mostCommonAction) {
            return null;
        }

        return [
            'action' => $mostCommonAction->action,
            'count' => $mostCommonAction->action_count,
        ];
    }
}