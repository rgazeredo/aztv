<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Models\TenantSettings;
use App\Services\TenantSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantSettingsController extends Controller
{
    public function __construct(private TenantSettingsService $settingsService)
    {
        $this->middleware('auth');
        $this->middleware('tenant');
    }

    /**
     * Display the main settings page with all categories
     */
    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant;
        $categories = TenantSettings::getDefaultCategories();
        $allSettings = $this->settingsService->getAllSettings($tenant);

        return view('settings.index', compact('categories', 'allSettings', 'tenant'));
    }

    /**
     * Display settings for a specific category
     */
    public function show(Request $request, string $category): View|JsonResponse
    {
        $tenant = $request->user()->tenant;

        try {
            $settingsData = $this->settingsService->getSettingsWithMetadata($tenant, $category);

            if ($request->expectsJson()) {
                return response()->json($settingsData);
            }

            return view('settings.category', array_merge($settingsData, [
                'category_key' => $category,
                'tenant' => $tenant,
            ]));

        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            abort(404, $e->getMessage());
        }
    }

    /**
     * Update settings for a specific category
     */
    public function update(UpdateTenantSettingsRequest $request): RedirectResponse|JsonResponse
    {
        $tenant = $request->user()->tenant;
        $category = $request->input('category');
        $settings = $request->input('settings', []);

        try {
            $results = $this->settingsService->updateCategory($tenant, $category, $settings);

            $message = 'Configurações atualizadas com sucesso!';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'results' => $results,
                ]);
            }

            return redirect()
                ->route('settings.show', $category)
                ->with('success', $message);

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
     * Reset a category to default values
     */
    public function reset(Request $request, string $category): RedirectResponse|JsonResponse
    {
        $tenant = $request->user()->tenant;

        try {
            $this->settingsService->resetCategory($tenant, $category);

            $message = 'Configurações resetadas para valores padrão!';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message]);
            }

            return redirect()
                ->route('settings.show', $category)
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
     * Export settings as JSON
     */
    public function export(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $exportData = $this->settingsService->exportSettings($tenant);

        return response()->json($exportData)
            ->header('Content-Disposition', 'attachment; filename="tenant-' . $tenant->id . '-settings.json"');
    }

    /**
     * Import settings from JSON
     */
    public function import(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'settings_file' => 'required|file|mimes:json',
        ]);

        $tenant = $request->user()->tenant;

        try {
            $fileContent = file_get_contents($request->file('settings_file')->getRealPath());
            $importData = json_decode($fileContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Arquivo JSON inválido');
            }

            if (!isset($importData['settings'])) {
                throw new \InvalidArgumentException('Formato de arquivo inválido');
            }

            $this->settingsService->importSettings($tenant, $importData['settings']);

            $message = 'Configurações importadas com sucesso!';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message]);
            }

            return redirect()
                ->route('settings.index')
                ->with('success', $message);

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
    public function getValue(Request $request, string $key): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $default = $request->input('default');

        $value = $this->settingsService->get($tenant, $key, $default);

        return response()->json([
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * Set a specific setting value (API endpoint)
     */
    public function setValue(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'category' => 'required|string',
            'value' => 'required',
            'type' => 'sometimes|string|in:string,integer,boolean,json,array,float',
        ]);

        $tenant = $request->user()->tenant;
        $category = $request->input('category');
        $value = $request->input('value');
        $type = $request->input('type', 'string');

        try {
            $setting = $this->settingsService->set($tenant, $category, $key, $value, $type);

            return response()->json([
                'message' => 'Configuração atualizada com sucesso',
                'setting' => [
                    'key' => $setting->key,
                    'value' => $setting->getValue(),
                    'category' => $setting->category,
                    'type' => $setting->type,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao atualizar configuração: ' . $e->getMessage(),
            ], 500);
        }
    }
}