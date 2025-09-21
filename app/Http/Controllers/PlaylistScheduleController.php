<?php

namespace App\Http\Controllers;

use App\Models\PlaylistSchedule;
use App\Models\Playlist;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class PlaylistScheduleController extends Controller
{
    protected ScheduleService $scheduleService;

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $query = PlaylistSchedule::forTenant($tenantId)
            ->with(['playlist']);

        // Apply filters
        if ($request->filled('playlist_id')) {
            $query->where('playlist_id', $request->playlist_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $schedules = $query->orderBy('priority', 'desc')
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->paginate(15)
            ->withQueryString();

        $playlists = Playlist::where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->get();

        return Inertia::render('PlaylistSchedules/Index', [
            'schedules' => $schedules,
            'playlists' => $playlists,
            'filters' => $request->only(['playlist_id', 'status', 'search']),
        ]);
    }

    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $playlists = Playlist::where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->get();

        return Inertia::render('PlaylistSchedules/Create', [
            'playlists' => $playlists,
            'playlistId' => $request->get('playlist_id'),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'playlist_id' => 'required|exists:playlists,id',
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:0|max:6',
            'priority' => 'integer|min:1|max:100',
            'is_active' => 'boolean',
            'check_conflicts' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $schedule = $this->scheduleService->createSchedule(
                $request->playlist_id,
                $request->validated()
            );

            return redirect()
                ->route('playlist-schedules.show', $schedule)
                ->with('success', 'Agendamento criado com sucesso.');

        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors(['general' => $e->getMessage()])
                ->withInput();
        }
    }

    public function show(PlaylistSchedule $playlistSchedule)
    {
        $this->authorize('view', $playlistSchedule);

        $playlistSchedule->load(['playlist', 'tenant']);

        // Get conflicts
        $conflicts = $this->scheduleService->checkScheduleConflicts(
            $playlistSchedule->toArray(),
            $playlistSchedule->id
        );

        // Get schedule preview for next 7 days
        $preview = $this->scheduleService->getSchedulePreview(
            $playlistSchedule->toArray(),
            7
        );

        return Inertia::render('PlaylistSchedules/Show', [
            'schedule' => $playlistSchedule,
            'conflicts' => $conflicts,
            'preview' => $preview,
        ]);
    }

    public function edit(PlaylistSchedule $playlistSchedule)
    {
        $this->authorize('update', $playlistSchedule);

        $tenantId = auth()->user()->tenant_id;

        $playlists = Playlist::where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->get();

        return Inertia::render('PlaylistSchedules/Edit', [
            'schedule' => $playlistSchedule,
            'playlists' => $playlists,
        ]);
    }

    public function update(Request $request, PlaylistSchedule $playlistSchedule)
    {
        $this->authorize('update', $playlistSchedule);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:0|max:6',
            'priority' => 'integer|min:1|max:100',
            'is_active' => 'boolean',
            'check_conflicts' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $this->scheduleService->updateSchedule(
                $playlistSchedule,
                $request->validated()
            );

            return redirect()
                ->route('playlist-schedules.show', $playlistSchedule)
                ->with('success', 'Agendamento atualizado com sucesso.');

        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors(['general' => $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(PlaylistSchedule $playlistSchedule)
    {
        $this->authorize('delete', $playlistSchedule);

        $playlistSchedule->delete();

        return redirect()
            ->route('playlist-schedules.index')
            ->with('success', 'Agendamento excluído com sucesso.');
    }

    public function toggle(Request $request, PlaylistSchedule $playlistSchedule)
    {
        $this->authorize('update', $playlistSchedule);

        $playlistSchedule->update([
            'is_active' => !$playlistSchedule->is_active
        ]);

        $status = $playlistSchedule->is_active ? 'ativado' : 'desativado';

        return response()->json([
            'success' => true,
            'message' => "Agendamento {$status} com sucesso.",
            'is_active' => $playlistSchedule->is_active,
        ]);
    }

    public function duplicate(PlaylistSchedule $playlistSchedule)
    {
        $this->authorize('view', $playlistSchedule);

        try {
            $duplicatedSchedule = $this->scheduleService->duplicateSchedule($playlistSchedule);

            return redirect()
                ->route('playlist-schedules.edit', $duplicatedSchedule)
                ->with('success', 'Agendamento duplicado com sucesso. Faça as alterações necessárias.');

        } catch (\Exception $e) {
            return back()
                ->with('error', 'Erro ao duplicar agendamento: ' . $e->getMessage());
        }
    }

    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:0|max:6',
            'priority' => 'integer|min:1|max:100',
            'tenant_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $preview = $this->scheduleService->getSchedulePreview(
                $request->validated(),
                $request->get('days', 7)
            );

            $conflicts = $this->scheduleService->checkScheduleConflicts(
                $request->validated(),
                $request->get('exclude_schedule_id')
            );

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'conflicts' => $conflicts->map(function ($conflict) {
                    return [
                        'id' => $conflict->id,
                        'name' => $conflict->name,
                        'playlist_name' => $conflict->playlist->name,
                        'time_range' => $conflict->getFormattedTimeRange(),
                        'date_range' => $conflict->getFormattedDateRange(),
                        'priority' => $conflict->priority,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete',
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'exists:playlist_schedules,id',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $schedules = PlaylistSchedule::whereIn('id', $request->schedule_ids)
            ->forTenant($tenantId)
            ->get();

        $count = 0;

        foreach ($schedules as $schedule) {
            try {
                switch ($request->action) {
                    case 'activate':
                        $this->authorize('update', $schedule);
                        $schedule->update(['is_active' => true]);
                        $count++;
                        break;

                    case 'deactivate':
                        $this->authorize('update', $schedule);
                        $schedule->update(['is_active' => false]);
                        $count++;
                        break;

                    case 'delete':
                        $this->authorize('delete', $schedule);
                        $schedule->delete();
                        $count++;
                        break;
                }
            } catch (\Exception $e) {
                // Skip unauthorized or failed operations
                continue;
            }
        }

        $actionNames = [
            'activate' => 'ativados',
            'deactivate' => 'desativados',
            'delete' => 'excluídos',
        ];

        return response()->json([
            'success' => true,
            'message' => "{$count} agendamentos {$actionNames[$request->action]} com sucesso.",
        ]);
    }
}