<?php

namespace App\Http\Middleware;

use App\Services\PlaylistScheduleValidationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class ScheduleValidationMiddleware
{
    private PlaylistScheduleValidationService $validationService;

    public function __construct(PlaylistScheduleValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only validate for POST and PUT requests (create/update schedules)
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        // Extract schedule data from request
        $scheduleData = $this->extractScheduleData($request);

        // Skip validation if no schedule data found
        if (empty($scheduleData)) {
            return $next($request);
        }

        try {
            // Get exclude ID for updates
            $excludeId = $this->getExcludeId($request);

            // Validate the schedule data
            $validatedData = $this->validationService->validateSchedule($scheduleData, $excludeId);

            // Replace request data with validated data
            $request->merge($validatedData);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados de agendamento inválidos.',
                'errors' => $e->errors(),
                'type' => 'validation_error'
            ], 422);

        } catch (Exception $e) {
            $errorDetails = $this->validationService->getValidationErrors($e);

            return response()->json([
                'message' => $errorDetails['message'],
                'errors' => $errorDetails['errors'],
                'type' => $errorDetails['type']
            ], 422);
        }

        return $next($request);
    }

    /**
     * Extract schedule data from request
     */
    private function extractScheduleData(Request $request): array
    {
        $scheduleFields = [
            'playlist_id',
            'tenant_id',
            'name',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
            'days_of_week',
            'priority',
            'is_active'
        ];

        $scheduleData = [];

        foreach ($scheduleFields as $field) {
            if ($request->has($field)) {
                $scheduleData[$field] = $request->input($field);
            }
        }

        // Auto-fill tenant_id from authenticated user if not provided
        if (!isset($scheduleData['tenant_id']) && $request->user()) {
            $scheduleData['tenant_id'] = $request->user()->tenant_id;
        }

        return $scheduleData;
    }

    /**
     * Get schedule ID to exclude from conflict checking (for updates)
     */
    private function getExcludeId(Request $request): ?int
    {
        // For update requests, exclude the current schedule from conflict checking
        $routeParameters = $request->route()?->parameters();

        if (isset($routeParameters['schedule'])) {
            return is_object($routeParameters['schedule'])
                ? $routeParameters['schedule']->id
                : (int) $routeParameters['schedule'];
        }

        if (isset($routeParameters['playlistSchedule'])) {
            return is_object($routeParameters['playlistSchedule'])
                ? $routeParameters['playlistSchedule']->id
                : (int) $routeParameters['playlistSchedule'];
        }

        if (isset($routeParameters['id'])) {
            return (int) $routeParameters['id'];
        }

        return null;
    }
}