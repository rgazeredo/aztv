<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\ContentModule;
use App\Services\WeatherService;
use App\Services\CurrencyService;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class ContentApiController extends Controller
{
    public function getContent(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'types' => 'nullable|array',
            'types.*' => 'in:' . implode(',', ContentModule::AVAILABLE_TYPES),
        ]);

        $query = ContentModule::forTenant($player->tenant_id)->enabled();

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
                    'update_interval' => $module->getSetting('update_interval', 30),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'content' => $content,
                'last_updated' => now()->toISOString(),
                'cache_duration' => 300, // 5 minutes
            ],
        ]);
    }

    public function getWeather(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $module = ContentModule::forTenant($player->tenant_id)
            ->ofType(ContentModule::TYPE_WEATHER)
            ->enabled()
            ->first();

        if (!$module || !$module->hasRequiredSettings()) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo de clima não configurado',
            ], 404);
        }

        $content = $this->generateWeatherContent($module);

        return response()->json([
            'success' => true,
            'data' => $content,
        ]);
    }

    public function getCurrency(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $module = ContentModule::forTenant($player->tenant_id)
            ->ofType(ContentModule::TYPE_CURRENCY)
            ->enabled()
            ->first();

        if (!$module || !$module->hasRequiredSettings()) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo de cotações não configurado',
            ], 404);
        }

        $content = $this->generateCurrencyContent($module);

        return response()->json([
            'success' => true,
            'data' => $content,
        ]);
    }

    public function getQuote(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $module = ContentModule::forTenant($player->tenant_id)
            ->ofType(ContentModule::TYPE_QUOTES)
            ->enabled()
            ->first();

        if (!$module || !$module->hasRequiredSettings()) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo de frases não configurado',
            ], 404);
        }

        $content = $this->generateQuoteContent($module);

        return response()->json([
            'success' => true,
            'data' => $content,
        ]);
    }

    public function getHealthTip(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $module = ContentModule::forTenant($player->tenant_id)
            ->ofType(ContentModule::TYPE_HEALTH_TIPS)
            ->enabled()
            ->first();

        if (!$module || !$module->hasRequiredSettings()) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo de dicas de saúde não configurado',
            ], 404);
        }

        $content = $this->generateHealthTipContent($module);

        return response()->json([
            'success' => true,
            'data' => $content,
        ]);
    }

    public function getPriceTable(Request $request)
    {
        $player = $this->getAuthenticatedPlayer($request);

        if (!$player) {
            return $this->unauthorizedResponse();
        }

        $module = ContentModule::forTenant($player->tenant_id)
            ->ofType(ContentModule::TYPE_PRICE_TABLE)
            ->enabled()
            ->first();

        if (!$module || !$module->hasRequiredSettings()) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo de tabela de preços não configurado',
            ], 404);
        }

        $content = $this->generatePriceTableContent($module);

        return response()->json([
            'success' => true,
            'data' => $content,
        ]);
    }

    private function getAuthenticatedPlayer(Request $request): ?Player
    {
        $playerId = $request->header('X-Player-ID');
        $apiToken = $request->header('X-API-Token');

        if (!$playerId || !$apiToken) {
            return null;
        }

        $cachedToken = cache()->get("player_token_{$playerId}");

        if (!$cachedToken || $cachedToken !== $apiToken) {
            return null;
        }

        return Player::find($playerId);
    }

    private function unauthorizedResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Não autorizado',
            'error_code' => 'UNAUTHORIZED',
        ], 401);
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

        if (empty($apiKey) || empty($city)) {
            return null;
        }

        try {
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
                'feels_like' => $weather['feels_like'] ?? null,
                'pressure' => $weather['pressure'] ?? null,
                'visibility' => $weather['visibility'] ?? null,
                'uv_index' => $weather['uv_index'] ?? null,
                'last_updated' => now()->format('H:i'),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateCurrencyContent(ContentModule $module): ?array
    {
        $currencies = $module->getSetting('currencies', []);

        if (empty($currencies)) {
            return null;
        }

        try {
            $currencyService = new CurrencyService();
            $rates = $currencyService->getRates($currencies);

            return [
                'type' => 'currency',
                'rates' => $rates,
                'base_currency' => 'BRL',
                'last_updated' => now()->format('H:i'),
                'source' => 'Banco Central',
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateQuoteContent(ContentModule $module): ?array
    {
        $category = $module->getSetting('category');

        try {
            $quoteService = new QuoteService();
            $quote = $quoteService->getQuote($category);

            return [
                'type' => 'quote',
                'text' => $quote['text'],
                'author' => $quote['author'],
                'category' => $category,
                'background_color' => $quote['background_color'] ?? '#f8f9fa',
                'text_color' => $quote['text_color'] ?? '#333333',
            ];
        } catch (\Exception $e) {
            return [
                'type' => 'quote',
                'text' => 'O sucesso é a soma de pequenos esforços repetidos dia após dia.',
                'author' => 'Robert Collier',
                'category' => $category,
                'background_color' => '#f8f9fa',
                'text_color' => '#333333',
            ];
        }
    }

    private function generateHealthTipContent(ContentModule $module): ?array
    {
        $category = $module->getSetting('category');

        $tips = [
            'general' => [
                'Beba pelo menos 8 copos de água por dia para manter-se hidratado.',
                'Durma entre 7-8 horas por noite para uma recuperação adequada.',
                'Faça exercícios regularmente, pelo menos 30 minutos por dia.',
                'Mantenha uma postura correta ao sentar e caminhar.',
                'Lave as mãos frequentemente para prevenir infecções.',
            ],
            'nutrition' => [
                'Inclua frutas e vegetais em todas as refeições.',
                'Evite alimentos ultraprocessados e ricos em açúcar.',
                'Faça refeições menores e mais frequentes ao longo do dia.',
                'Consuma proteínas magras como peixe, frango e leguminosas.',
                'Reduza o consumo de sal e prefira temperos naturais.',
            ],
            'exercise' => [
                'Comece com exercícios leves e aumente gradualmente a intensidade.',
                'Alterne entre exercícios cardiovasculares e de força.',
                'Faça alongamentos antes e depois dos exercícios.',
                'Encontre uma atividade física que você goste de praticar.',
                'Descanse adequadamente entre os treinos.',
            ],
            'mental' => [
                'Pratique meditação ou mindfulness por alguns minutos diários.',
                'Mantenha conexões sociais positivas.',
                'Reserve tempo para hobbies e atividades prazerosas.',
                'Gerencie o estresse através de técnicas de respiração.',
                'Busque ajuda profissional quando necessário.',
            ],
        ];

        $categoryTips = $tips[$category] ?? $tips['general'];
        $randomTip = $categoryTips[array_rand($categoryTips)];

        return [
            'type' => 'health_tip',
            'tip' => $randomTip,
            'category' => $category,
            'icon' => $this->getHealthTipIcon($category),
            'background_color' => $this->getHealthTipColor($category),
        ];
    }

    private function generateFunnyVideoContent(ContentModule $module): ?array
    {
        $source = $module->getSetting('source');
        $durationLimit = $module->getSetting('duration_limit');

        $sampleVideos = [
            [
                'title' => 'Vídeo Engraçado 1',
                'url' => 'https://example.com/funny-video-1.mp4',
                'duration' => 45,
                'thumbnail' => 'https://example.com/thumb-1.jpg',
            ],
            [
                'title' => 'Vídeo Engraçado 2',
                'url' => 'https://example.com/funny-video-2.mp4',
                'duration' => 60,
                'thumbnail' => 'https://example.com/thumb-2.jpg',
            ],
        ];

        $randomVideo = $sampleVideos[array_rand($sampleVideos)];
        $randomVideo['duration'] = min($randomVideo['duration'], $durationLimit);

        return [
            'type' => 'funny_video',
            'source' => $source,
            'video_url' => $randomVideo['url'],
            'duration' => $randomVideo['duration'],
            'title' => $randomVideo['title'],
            'thumbnail' => $randomVideo['thumbnail'],
        ];
    }

    private function generatePriceTableContent(ContentModule $module): ?array
    {
        $excelFile = $module->getSetting('excel_file');

        $samplePrices = [
            ['product' => 'Produto A', 'price' => 'R$ 29,90', 'category' => 'Categoria 1'],
            ['product' => 'Produto B', 'price' => 'R$ 49,90', 'category' => 'Categoria 1'],
            ['product' => 'Produto C', 'price' => 'R$ 79,90', 'category' => 'Categoria 2'],
            ['product' => 'Produto D', 'price' => 'R$ 99,90', 'category' => 'Categoria 2'],
        ];

        return [
            'type' => 'price_table',
            'file_path' => $excelFile,
            'last_updated' => now()->format('d/m/Y H:i'),
            'items' => $samplePrices,
            'categories' => array_unique(array_column($samplePrices, 'category')),
            'total_items' => count($samplePrices),
        ];
    }

    private function getHealthTipIcon(string $category): string
    {
        return match($category) {
            'nutrition' => '🥗',
            'exercise' => '💪',
            'mental' => '🧠',
            default => '❤️',
        };
    }

    private function getHealthTipColor(string $category): string
    {
        return match($category) {
            'nutrition' => '#28a745',
            'exercise' => '#007bff',
            'mental' => '#6f42c1',
            default => '#dc3545',
        };
    }
}