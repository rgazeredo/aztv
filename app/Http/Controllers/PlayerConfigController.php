<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePlayerConfigRequest;
use App\Models\Player;
use App\Models\PlayerSettings;
use App\Services\PlayerConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerConfigController extends Controller
{
    public function __construct(private PlayerConfigService $configService)
    {
        $this->middleware('auth');
        $this->middleware('tenant');
    }

    /**
     * Display player configuration page
     */
    public function index(Request $request, Player $player): View
    {
        $this->authorize('update', $player);

        $settingsWithMetadata = $this->configService->getSettingsWithMetadata($player);
        $availableSettings = PlayerSettings::getAvailableSettings();

        return view('players.config', compact('player', 'settingsWithMetadata', 'availableSettings'));
    }

    /**
     * Get player configuration (API endpoint)
     */
    public function show(Request $request, Player $player): JsonResponse
    {
        $this->authorize('view', $player);

        $includeMetadata = $request->boolean('include_metadata', false);

        if ($includeMetadata) {
            $data = $this->configService->getSettingsWithMetadata($player);
        } else {
            $data = $this->configService->getEffectiveConfig($player);
        }

        return response()->json([
            'player_id' => $player->id,
            'player_name' => $player->name,
            'config' => $data,
        ]);
    }

    /**
     * Update player configuration
     */
    public function update(UpdatePlayerConfigRequest $request, Player $player): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $player);

        $settings = $request->input('settings', []);

        try {
            $results = $this->configService->updateSettings($player, $settings);

            $successCount = count($results['success']);
            $errorCount = count($results['errors']);

            if ($errorCount === 0) {
                $message = "Configurações atualizadas com sucesso! ({$successCount} configurações)";
                $status = 'success';
            } else {
                $message = "Configurações parcialmente atualizadas. {$successCount} sucessos, {$errorCount} erros.";
                $status = 'warning';
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'status' => $status,
                    'results' => $results,
                ]);
            }

            return redirect()
                ->route('players.config.index', $player)
                ->with($status, $message);

        } catch (\Exception $e) {
            $message = 'Erro ao atualizar configurações: ' . $e->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 500);
            }

            return redirect()
                ->back()
                ->with('error', $message)
                ->withInput();
        }
    }

    /**
     * Reset specific setting to default
     */
    public function resetSetting(Request $request, Player $player, string $settingKey): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $player);

        try {
            $reset = $this->configService->resetToDefault($player, $settingKey);

            if ($reset) {
                $message = "Configuração '{$settingKey}' resetada para o padrão com sucesso!";
            } else {
                $message = "Configuração '{$settingKey}' já estava usando o valor padrão.";
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $message]);
            }

            return redirect()
                ->back()
                ->with('success', $message);

        } catch (\Exception $e) {
            $message = 'Erro ao resetar configuração: ' . $e->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 500);
            }

            return redirect()
                ->back()
                ->with('error', $message);
        }
    }

    /**
     * Reset all settings to defaults
     */
    public function resetAll(Request $request, Player $player): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $player);

        try {
            $count = $this->configService->resetAllToDefaults($player);

            $message = "Todas as configurações resetadas para padrão! ({$count} configurações removidas)";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'count' => $count]);
            }

            return redirect()
                ->route('players.config.index', $player)
                ->with('success', $message);

        } catch (\Exception $e) {
            $message = 'Erro ao resetar configurações: ' . $e->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 500);
            }

            return redirect()
                ->back()
                ->with('error', $message);
        }
    }

    /**
     * Export player configuration
     */
    public function export(Request $request, Player $player): JsonResponse
    {
        $this->authorize('view', $player);

        $exportData = $this->configService->exportConfig($player);

        return response()->json($exportData)
            ->header('Content-Disposition', 'attachment; filename="player-' . $player->id . '-config.json"');
    }

    /**
     * Import player configuration
     */
    public function import(Request $request, Player $player): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $player);

        $request->validate([
            'config_file' => 'required|file|mimes:json',
        ]);

        try {
            $fileContent = file_get_contents($request->file('config_file')->getRealPath());
            $importData = json_decode($fileContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Arquivo JSON inválido');
            }

            if (!isset($importData['settings']) && !isset($importData['custom_settings'])) {
                throw new \InvalidArgumentException('Formato de arquivo inválido');
            }

            // Use custom_settings if available, otherwise use settings
            $settings = $importData['custom_settings'] ?? $importData['settings'];
            $results = $this->configService->importConfig($player, $settings);

            $successCount = count(array_filter($results, fn($r) => !isset($r['error'])));
            $errorCount = count($results) - $successCount;

            if ($errorCount === 0) {
                $message = "Configurações importadas com sucesso! ({$successCount} configurações)";
                $status = 'success';
            } else {
                $message = "Configurações parcialmente importadas. {$successCount} sucessos, {$errorCount} erros.";
                $status = 'warning';
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'status' => $status,
                    'results' => $results,
                ]);
            }

            return redirect()
                ->route('players.config.index', $player)
                ->with($status, $message);

        } catch (\Exception $e) {
            $message = 'Erro ao importar configurações: ' . $e->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 500);
            }

            return redirect()
                ->back()
                ->with('error', $message);
        }
    }

    /**
     * Get a specific setting value (API endpoint)
     */
    public function getSetting(Request $request, Player $player, string $settingKey): JsonResponse
    {
        $this->authorize('view', $player);

        $default = $request->input('default');
        $value = $this->configService->getSetting($player, $settingKey, $default);

        return response()->json([
            'player_id' => $player->id,
            'setting_key' => $settingKey,
            'value' => $value,
        ]);
    }

    /**
     * Set a specific setting value (API endpoint)
     */
    public function setSetting(Request $request, Player $player, string $settingKey): JsonResponse
    {
        $this->authorize('update', $player);

        $request->validate([
            'value' => 'required',
            'type' => 'sometimes|string|in:string,integer,boolean,json,float',
        ]);

        $value = $request->input('value');
        $type = $request->input('type', 'string');

        try {
            $setting = $this->configService->setSetting($player, $settingKey, $value, $type);

            return response()->json([
                'message' => 'Configuração atualizada com sucesso',
                'setting' => [
                    'key' => $setting->setting_key,
                    'value' => $setting->setting_value,
                    'type' => $setting->setting_type,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao atualizar configuração: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get configuration for player sync (API endpoint)
     */
    public function getConfigForSync(Request $request, Player $player): JsonResponse
    {
        // This endpoint might be called by the player itself, so we use a different authorization
        if (!$request->hasValidSignature() && !$player->isValidToken($request->bearerToken())) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $configData = $this->configService->getConfigForSync($player);

        return response()->json($configData);
    }

    /**
     * Generate a random password for the player
     */
    public function generatePassword(Request $request): JsonResponse
    {
        $length = $request->input('length', 8);
        $length = max(4, min(50, $length)); // Ensure length is between 4 and 50

        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return response()->json([
            'password' => $password,
            'length' => $length,
        ]);
    }

    /**
     * Preview theme configuration
     */
    public function previewTheme(Request $request): JsonResponse
    {
        $request->validate([
            'theme' => 'required|array',
            'theme.primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme.secondary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme.background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme.text_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $theme = $request->input('theme');

        // Generate CSS variables for preview
        $cssVars = [
            '--primary-color' => $theme['primary_color'],
            '--secondary-color' => $theme['secondary_color'],
            '--background-color' => $theme['background_color'],
            '--text-color' => $theme['text_color'],
        ];

        return response()->json([
            'theme' => $theme,
            'css_vars' => $cssVars,
            'preview_url' => route('players.config.theme-preview', ['theme' => base64_encode(json_encode($theme))]),
        ]);
    }
}