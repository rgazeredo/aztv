<?php

namespace App\Http\Controllers;

use App\Models\ContentModule;
use App\Services\WeatherService;
use App\Services\CurrencyService;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentModuleController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $query = ContentModule::forTenant($tenantId);

        if ($type = $request->get('type')) {
            $query->ofType($type);
        }

        if ($status = $request->get('status')) {
            if ($status === 'enabled') {
                $query->enabled();
            } elseif ($status === 'disabled') {
                $query->disabled();
            }
        }

        $modules = $query->orderBy('type')
            ->get()
            ->map(function ($module) {
                return [
                    'id' => $module->id,
                    'type' => $module->type,
                    'display_name' => $module->getDisplayName(),
                    'description' => $module->getDescription(),
                    'is_enabled' => $module->is_enabled,
                    'settings' => $module->settings,
                    'has_required_settings' => $module->hasRequiredSettings(),
                    'created_at' => $module->created_at,
                ];
            });

        $availableTypes = ContentModule::getTypesWithDescriptions();

        $existingTypes = $modules->pluck('type');
        $missingTypes = collect($availableTypes)->reject(function ($typeData, $type) use ($existingTypes) {
            return $existingTypes->contains($type);
        });

        return Inertia::render('ContentModule/Index', [
            'modules' => $modules,
            'available_types' => $availableTypes,
            'missing_types' => $missingTypes,
            'filters' => $request->only(['type', 'status']),
            'stats' => [
                'total_modules' => $modules->count(),
                'enabled_modules' => $modules->where('is_enabled', true)->count(),
                'ready_modules' => $modules->where('has_required_settings', true)->where('is_enabled', true)->count(),
            ],
        ]);
    }

    public function show(ContentModule $module): Response
    {
        $this->authorize('view', $module);

        return Inertia::render('ContentModule/Show', [
            'module' => [
                'id' => $module->id,
                'type' => $module->type,
                'display_name' => $module->getDisplayName(),
                'description' => $module->getDescription(),
                'is_enabled' => $module->is_enabled,
                'settings' => $module->settings,
                'default_settings' => $module->getDefaultSettings(),
                'has_required_settings' => $module->hasRequiredSettings(),
                'created_at' => $module->created_at,
            ],
        ]);
    }

    public function create(): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $existingTypes = ContentModule::forTenant($tenantId)->pluck('type');
        $availableTypes = ContentModule::getTypesWithDescriptions();

        $missingTypes = collect($availableTypes)->reject(function ($typeData, $type) use ($existingTypes) {
            return $existingTypes->contains($type);
        });

        return Inertia::render('ContentModule/Create', [
            'available_types' => $missingTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', ContentModule::AVAILABLE_TYPES),
            'settings' => 'nullable|array',
        ]);

        $tenantId = auth()->user()->tenant_id;

        if (ContentModule::forTenant($tenantId)->ofType($validated['type'])->exists()) {
            return back()->with('error', 'Este tipo de módulo já existe para este cliente.');
        }

        $module = ContentModule::createForTenant(
            $tenantId,
            $validated['type'],
            $validated['settings'] ?? []
        );

        return redirect()->route('content-modules.show', $module)
            ->with('success', 'Módulo de conteúdo criado com sucesso!');
    }

    public function edit(ContentModule $module): Response
    {
        $this->authorize('update', $module);

        return Inertia::render('ContentModule/Edit', [
            'module' => [
                'id' => $module->id,
                'type' => $module->type,
                'display_name' => $module->getDisplayName(),
                'description' => $module->getDescription(),
                'settings' => $module->settings,
                'default_settings' => $module->getDefaultSettings(),
            ],
        ]);
    }

    public function update(Request $request, ContentModule $module)
    {
        $this->authorize('update', $module);

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        $this->validateModuleSettings($module->type, $validated['settings']);

        $module->updateSettings($validated['settings']);

        return redirect()->route('content-modules.show', $module)
            ->with('success', 'Configurações do módulo atualizadas com sucesso!');
    }

    public function destroy(ContentModule $module)
    {
        $this->authorize('delete', $module);

        $module->delete();

        return redirect()->route('content-modules.index')
            ->with('success', 'Módulo de conteúdo excluído com sucesso!');
    }

    public function toggle(ContentModule $module)
    {
        $this->authorize('update', $module);

        if (!$module->is_enabled && !$module->hasRequiredSettings()) {
            return back()->with('error', 'Configure as configurações obrigatórias antes de ativar o módulo.');
        }

        $module->toggle();

        $status = $module->is_enabled ? 'ativado' : 'desativado';

        return back()->with('success', "Módulo {$status} com sucesso!");
    }

    public function testConnection(ContentModule $module)
    {
        $this->authorize('view', $module);

        $result = $this->testModuleConnection($module);

        return response()->json($result);
    }

    public function preview(ContentModule $module)
    {
        $this->authorize('view', $module);

        if (!$module->hasRequiredSettings()) {
            return response()->json([
                'success' => false,
                'message' => 'Configure as configurações obrigatórias primeiro.',
            ]);
        }

        $content = $this->generateModuleContent($module);

        return response()->json([
            'success' => true,
            'content' => $content,
        ]);
    }

    public function bulkToggle(Request $request)
    {
        $validated = $request->validate([
            'module_ids' => 'required|array|min:1',
            'module_ids.*' => 'exists:content_modules,id',
            'enable' => 'required|boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $modules = ContentModule::whereIn('id', $validated['module_ids'])
            ->forTenant($tenantId)
            ->get();

        $updatedCount = 0;
        $errors = [];

        foreach ($modules as $module) {
            if ($validated['enable'] && !$module->hasRequiredSettings()) {
                $errors[] = "Módulo {$module->getDisplayName()} não possui configurações obrigatórias.";
                continue;
            }

            $module->update(['is_enabled' => $validated['enable']]);
            $updatedCount++;
        }

        $action = $validated['enable'] ? 'ativados' : 'desativados';
        $message = "{$updatedCount} módulo(s) {$action} com sucesso!";

        if (!empty($errors)) {
            $message .= ' Erros: ' . implode(', ', $errors);
        }

        return back()->with('success', $message);
    }

    public function getContent(Request $request)
    {
        $validated = $request->validate([
            'types' => 'nullable|array',
            'types.*' => 'in:' . implode(',', ContentModule::AVAILABLE_TYPES),
        ]);

        $tenantId = auth()->user()->tenant_id;

        $query = ContentModule::forTenant($tenantId)->enabled();

        if (!empty($validated['types'])) {
            $query->whereIn('type', $validated['types']);
        }

        $modules = $query->get()->filter(function ($module) {
            return $module->hasRequiredSettings();
        });

        $content = [];

        foreach ($modules as $module) {
            $moduleContent = $this->generateModuleContent($module);
            if ($moduleContent) {
                $content[] = [
                    'type' => $module->type,
                    'display_name' => $module->getDisplayName(),
                    'content' => $moduleContent,
                    'display_duration' => $module->getSetting('display_duration', 10),
                ];
            }
        }

        return response()->json(['content' => $content]);
    }

    private function validateModuleSettings(string $type, array $settings): void
    {
        $rules = match($type) {
            ContentModule::TYPE_WEATHER => [
                'city' => 'required|string|max:100',
                'api_key' => 'required|string|max:100',
                'update_interval' => 'required|integer|min:5|max:120',
                'display_duration' => 'required|integer|min:5|max:60',
            ],
            ContentModule::TYPE_CURRENCY => [
                'currencies' => 'required|array|min:1',
                'update_interval' => 'required|integer|min:5|max:120',
                'display_duration' => 'required|integer|min:5|max:60',
            ],
            ContentModule::TYPE_QUOTES => [
                'category' => 'required|string|in:motivational,business,life,success',
                'rotation_interval' => 'required|integer|min:30|max:240',
                'display_duration' => 'required|integer|min:10|max:30',
            ],
            ContentModule::TYPE_HEALTH_TIPS => [
                'category' => 'required|string|in:general,nutrition,exercise,mental',
                'rotation_interval' => 'required|integer|min:60|max:480',
                'display_duration' => 'required|integer|min:15|max:45',
            ],
            ContentModule::TYPE_FUNNY_VIDEOS => [
                'source' => 'required|string|in:youtube,local',
                'duration_limit' => 'required|integer|min:30|max:300',
                'rotation_interval' => 'required|integer|min:120|max:720',
            ],
            ContentModule::TYPE_PRICE_TABLE => [
                'excel_file' => 'required|string|max:255',
                'update_interval' => 'required|integer|min:30|max:240',
                'display_duration' => 'required|integer|min:20|max:60',
            ],
            default => [],
        };

        if (!empty($rules)) {
            validator($settings, $rules)->validate();
        }
    }

    private function testModuleConnection(ContentModule $module): array
    {
        try {
            return match($module->type) {
                ContentModule::TYPE_WEATHER => $this->testWeatherConnection($module),
                ContentModule::TYPE_CURRENCY => $this->testCurrencyConnection($module),
                ContentModule::TYPE_QUOTES => $this->testQuoteConnection($module),
                default => ['success' => true, 'message' => 'Módulo não requer teste de conexão.'],
            };
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage(),
            ];
        }
    }

    private function testWeatherConnection(ContentModule $module): array
    {
        $apiKey = $module->getSetting('api_key');
        $city = $module->getSetting('city');

        if (empty($apiKey) || empty($city)) {
            return ['success' => false, 'message' => 'API key e cidade são obrigatórios.'];
        }

        $weatherService = new WeatherService($apiKey);
        $weather = $weatherService->getWeather($city);

        return [
            'success' => true,
            'message' => "Conexão bem-sucedida! Temperatura atual: {$weather['temperature']}°C",
            'data' => $weather,
        ];
    }

    private function testCurrencyConnection(ContentModule $module): array
    {
        $currencies = $module->getSetting('currencies', []);

        if (empty($currencies)) {
            return ['success' => false, 'message' => 'Selecione pelo menos uma moeda.'];
        }

        $currencyService = new CurrencyService();
        $rates = $currencyService->getRates($currencies);

        return [
            'success' => true,
            'message' => 'Conexão bem-sucedida! Cotações obtidas.',
            'data' => $rates,
        ];
    }

    private function testQuoteConnection(ContentModule $module): array
    {
        $category = $module->getSetting('category');

        $quoteService = new QuoteService();
        $quote = $quoteService->getQuote($category);

        return [
            'success' => true,
            'message' => 'Conexão bem-sucedida!',
            'data' => $quote,
        ];
    }

    private function generateModuleContent(ContentModule $module): ?array
    {
        try {
            return match($module->type) {
                ContentModule::TYPE_WEATHER => $this->generateWeatherContent($module),
                ContentModule::TYPE_CURRENCY => $this->generateCurrencyContent($module),
                ContentModule::TYPE_QUOTES => $this->generateQuoteContent($module),
                ContentModule::TYPE_HEALTH_TIPS => $this->generateHealthTipContent($module),
                ContentModule::TYPE_FUNNY_VIDEOS => $this->generateFunnyVideoContent($module),
                ContentModule::TYPE_PRICE_TABLE => $this->generatePriceTableContent($module),
                default => null,
            };
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateWeatherContent(ContentModule $module): ?array
    {
        $apiKey = $module->getSetting('api_key');
        $city = $module->getSetting('city');

        $weatherService = new WeatherService($apiKey);
        $weather = $weatherService->getWeather($city);

        return [
            'type' => 'weather',
            'city' => $city,
            'temperature' => $weather['temperature'],
            'description' => $weather['description'],
            'humidity' => $weather['humidity'],
            'wind_speed' => $weather['wind_speed'],
            'icon' => $weather['icon'],
        ];
    }

    private function generateCurrencyContent(ContentModule $module): ?array
    {
        $currencies = $module->getSetting('currencies', []);

        $currencyService = new CurrencyService();
        $rates = $currencyService->getRates($currencies);

        return [
            'type' => 'currency',
            'rates' => $rates,
            'last_update' => now()->format('H:i'),
        ];
    }

    private function generateQuoteContent(ContentModule $module): ?array
    {
        $category = $module->getSetting('category');

        $quoteService = new QuoteService();
        $quote = $quoteService->getQuote($category);

        return [
            'type' => 'quote',
            'text' => $quote['text'],
            'author' => $quote['author'],
            'category' => $category,
        ];
    }

    private function generateHealthTipContent(ContentModule $module): ?array
    {
        $category = $module->getSetting('category');

        $tips = [
            'general' => [
                'Beba pelo menos 8 copos de água por dia',
                'Durma entre 7-8 horas por noite',
                'Faça exercícios regularmente',
            ],
            'nutrition' => [
                'Inclua frutas e vegetais em todas as refeições',
                'Evite alimentos processados',
                'Faça refeições menores e mais frequentes',
            ],
        ];

        $categoryTips = $tips[$category] ?? $tips['general'];
        $randomTip = $categoryTips[array_rand($categoryTips)];

        return [
            'type' => 'health_tip',
            'tip' => $randomTip,
            'category' => $category,
        ];
    }

    private function generateFunnyVideoContent(ContentModule $module): ?array
    {
        $source = $module->getSetting('source');
        $durationLimit = $module->getSetting('duration_limit');

        return [
            'type' => 'funny_video',
            'source' => $source,
            'video_url' => 'https://example.com/funny-video.mp4',
            'duration' => min($durationLimit, 180),
            'title' => 'Vídeo Engraçado do Dia',
        ];
    }

    private function generatePriceTableContent(ContentModule $module): ?array
    {
        $excelFile = $module->getSetting('excel_file');

        return [
            'type' => 'price_table',
            'file_path' => $excelFile,
            'last_update' => now()->format('d/m/Y H:i'),
            'items' => [
                ['product' => 'Produto A', 'price' => 'R$ 29,90'],
                ['product' => 'Produto B', 'price' => 'R$ 49,90'],
            ],
        ];
    }
}