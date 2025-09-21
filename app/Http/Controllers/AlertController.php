<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlertRuleRequest;
use App\Http\Requests\UpdateAlertRuleRequest;
use App\Models\AlertRule;
use App\Models\Player;
use App\Services\AlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function __construct(private AlertService $alertService)
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of alert rules
     */
    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant;

        $alertRules = AlertRule::forTenant($tenant->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $statistics = $this->alertService->getAlertStatistics($tenant->id);

        return view('alerts.index', compact('alertRules', 'statistics', 'tenant'));
    }

    /**
     * Show the form for creating a new alert rule
     */
    public function create(Request $request): View
    {
        $tenant = $request->user()->tenant;
        $players = Player::forTenant($tenant->id)->orderBy('name')->get();
        $playerGroups = $players->pluck('group')->unique()->filter()->sort()->values();
        $availableTypes = AlertRule::getAvailableTypes();

        return view('alerts.create', compact('tenant', 'players', 'playerGroups', 'availableTypes'));
    }

    /**
     * Store a newly created alert rule
     */
    public function store(StoreAlertRuleRequest $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $alertRule = AlertRule::create([
            'tenant_id' => $tenant->id,
            'type' => $request->type,
            'condition' => $request->condition ?? [],
            'threshold' => $request->threshold,
            'recipients' => $request->recipients,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('alerts.index')
            ->with('success', 'Regra de alerta criada com sucesso!');
    }

    /**
     * Display the specified alert rule
     */
    public function show(Request $request, AlertRule $alertRule): View
    {
        $this->authorize('view', $alertRule);

        $tenant = $request->user()->tenant;

        return view('alerts.show', compact('alertRule', 'tenant'));
    }

    /**
     * Show the form for editing the specified alert rule
     */
    public function edit(Request $request, AlertRule $alertRule): View
    {
        $this->authorize('update', $alertRule);

        $tenant = $request->user()->tenant;
        $players = Player::forTenant($tenant->id)->orderBy('name')->get();
        $playerGroups = $players->pluck('group')->unique()->filter()->sort()->values();
        $availableTypes = AlertRule::getAvailableTypes();

        return view('alerts.edit', compact('alertRule', 'tenant', 'players', 'playerGroups', 'availableTypes'));
    }

    /**
     * Update the specified alert rule
     */
    public function update(UpdateAlertRuleRequest $request, AlertRule $alertRule): RedirectResponse
    {
        $this->authorize('update', $alertRule);

        $alertRule->update([
            'type' => $request->type,
            'condition' => $request->condition ?? [],
            'threshold' => $request->threshold,
            'recipients' => $request->recipients,
            'is_active' => $request->boolean('is_active', $alertRule->is_active),
        ]);

        return redirect()
            ->route('alerts.index')
            ->with('success', 'Regra de alerta atualizada com sucesso!');
    }

    /**
     * Remove the specified alert rule
     */
    public function destroy(Request $request, AlertRule $alertRule): RedirectResponse
    {
        $this->authorize('delete', $alertRule);

        $alertRule->delete();

        return redirect()
            ->route('alerts.index')
            ->with('success', 'Regra de alerta removida com sucesso!');
    }

    /**
     * Toggle alert rule active status
     */
    public function toggle(Request $request, AlertRule $alertRule): RedirectResponse
    {
        $this->authorize('update', $alertRule);

        $alertRule->update([
            'is_active' => !$alertRule->is_active
        ]);

        $status = $alertRule->is_active ? 'ativada' : 'desativada';

        return redirect()
            ->back()
            ->with('success', "Regra de alerta {$status} com sucesso!");
    }

    /**
     * Test alert rule by sending a test email
     */
    public function test(Request $request, AlertRule $alertRule): RedirectResponse
    {
        $this->authorize('update', $alertRule);

        $result = $this->alertService->testAlert($alertRule);

        if ($result['success']) {
            return redirect()
                ->back()
                ->with('success', $result['message']);
        } else {
            return redirect()
                ->back()
                ->with('error', $result['message']);
        }
    }
}