<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QuoteService
{
    private const CACHE_TTL = 3600; // 1 hour

    public function getRandomQuote(Tenant $tenant, ?string $category = null): ?Quote
    {
        $cacheKey = "quotes.random.{$tenant->id}." . ($category ?: 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant, $category) {
            $query = Quote::forTenant($tenant->id)->active();

            if ($category) {
                $query->byCategory($category);
            }

            $quote = $query->inRandomOrder()->first();

            Log::debug('Random quote fetched', [
                'tenant_id' => $tenant->id,
                'category' => $category,
                'quote_id' => $quote?->id,
            ]);

            return $quote;
        });
    }

    public function getRotationSequence(Tenant $tenant, array $options = []): Collection
    {
        $mode = $options['mode'] ?? 'sequential';
        $category = $options['category'] ?? null;
        $limit = $options['limit'] ?? 50;

        $cacheKey = "quotes.rotation.{$tenant->id}.{$mode}." . ($category ?: 'all') . ".{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant, $mode, $category, $limit) {
            $query = Quote::forTenant($tenant->id)->active();

            if ($category) {
                $query->byCategory($category);
            }

            $quotes = $query->limit($limit);

            switch ($mode) {
                case 'random':
                    $quotes = $quotes->inRandomOrder();
                    break;
                case 'newest':
                    $quotes = $quotes->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $quotes = $quotes->orderBy('created_at', 'asc');
                    break;
                case 'sequential':
                default:
                    $quotes = $quotes->orderBy('id');
                    break;
            }

            $result = $quotes->get();

            Log::debug('Rotation sequence generated', [
                'tenant_id' => $tenant->id,
                'mode' => $mode,
                'category' => $category,
                'count' => $result->count(),
            ]);

            return $result;
        });
    }

    public function getNextQuote(Tenant $tenant, ?int $currentQuoteId = null, array $options = []): ?Quote
    {
        $sequence = $this->getRotationSequence($tenant, $options);

        if ($sequence->isEmpty()) {
            return null;
        }

        if (!$currentQuoteId) {
            return $sequence->first();
        }

        $currentIndex = $sequence->search(fn($quote) => $quote->id === $currentQuoteId);

        if ($currentIndex === false) {
            return $sequence->first();
        }

        $nextIndex = ($currentIndex + 1) % $sequence->count();
        return $sequence->get($nextIndex);
    }

    public function seedDefaultQuotes(Tenant $tenant): int
    {
        $defaultQuotes = $this->getDefaultQuotes();
        $created = 0;

        foreach ($defaultQuotes as $quoteData) {
            $exists = Quote::forTenant($tenant->id)
                ->where('text', $quoteData['text'])
                ->exists();

            if (!$exists) {
                Quote::create([
                    'tenant_id' => $tenant->id,
                    'text' => $quoteData['text'],
                    'author' => $quoteData['author'],
                    'category' => $quoteData['category'],
                    'display_duration' => $quoteData['display_duration'] ?? 30,
                ]);
                $created++;
            }
        }

        $this->clearCache($tenant);

        Log::info('Default quotes seeded', [
            'tenant_id' => $tenant->id,
            'created' => $created,
            'total_available' => count($defaultQuotes),
        ]);

        return $created;
    }

    public function getQuotesByCategory(Tenant $tenant, string $category): Collection
    {
        $cacheKey = "quotes.category.{$tenant->id}.{$category}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant, $category) {
            return Quote::forTenant($tenant->id)
                ->active()
                ->byCategory($category)
                ->orderBy('created_at', 'desc')
                ->get();
        });
    }

    public function getStatistics(Tenant $tenant): array
    {
        $cacheKey = "quotes.stats.{$tenant->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant) {
            $total = Quote::forTenant($tenant->id)->count();
            $active = Quote::forTenant($tenant->id)->active()->count();
            $inactive = $total - $active;

            $byCategory = Quote::forTenant($tenant->id)
                ->active()
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray();

            return [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'by_category' => $byCategory,
            ];
        });
    }

    public function clearCache(Tenant $tenant): void
    {
        $patterns = [
            "quotes.random.{$tenant->id}.*",
            "quotes.rotation.{$tenant->id}.*",
            "quotes.category.{$tenant->id}.*",
            "quotes.stats.{$tenant->id}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        Log::debug('Quote cache cleared for tenant', [
            'tenant_id' => $tenant->id,
        ]);
    }

    private function getDefaultQuotes(): array
    {
        return [
            [
                'text' => 'O sucesso é a soma de pequenos esforços repetidos dia após dia.',
                'author' => 'Robert Collier',
                'category' => 'motivacional',
            ],
            [
                'text' => 'A persistência é o caminho do êxito.',
                'author' => 'Charles Chaplin',
                'category' => 'sucesso',
            ],
            [
                'text' => 'Grandes realizações requerem grandes ambições.',
                'author' => 'Heródoto',
                'category' => 'inspiracional',
            ],
            [
                'text' => 'A liderança é a capacidade de transformar visão em realidade.',
                'author' => 'Warren Bennis',
                'category' => 'liderança',
            ],
            [
                'text' => 'Inovação distingue um líder de um seguidor.',
                'author' => 'Steve Jobs',
                'category' => 'empresarial',
            ],
            [
                'text' => 'O único lugar onde o sucesso vem antes do trabalho é no dicionário.',
                'author' => 'Vidal Sassoon',
                'category' => 'motivacional',
            ],
            [
                'text' => 'Acredite em si mesmo e chegará um dia em que os outros não terão outra escolha senão acreditar com você.',
                'author' => 'Cynthia Kersey',
                'category' => 'inspiracional',
            ],
            [
                'text' => 'A qualidade nunca é um acidente; é sempre o resultado de um esforço inteligente.',
                'author' => 'John Ruskin',
                'category' => 'empresarial',
            ],
            [
                'text' => 'O fracasso é apenas a oportunidade de começar de novo de forma mais inteligente.',
                'author' => 'Henry Ford',
                'category' => 'sucesso',
            ],
            [
                'text' => 'Um líder é aquele que conhece o caminho, segue o caminho e mostra o caminho.',
                'author' => 'John C. Maxwell',
                'category' => 'liderança',
            ],
            [
                'text' => 'A disciplina é a ponte entre metas e conquistas.',
                'author' => 'Jim Rohn',
                'category' => 'motivacional',
            ],
            [
                'text' => 'Não espere por oportunidades extraordinárias. Agarre ocasiões comuns e as torne grandiosas.',
                'author' => 'Orison Swett Marden',
                'category' => 'inspiracional',
            ],
            [
                'text' => 'A excelência empresarial é fazer uma coisa comum de uma maneira incomum.',
                'author' => 'Booker T. Washington',
                'category' => 'empresarial',
            ],
            [
                'text' => 'O sucesso não é definitivo, o fracasso não é fatal: é a coragem de continuar que conta.',
                'author' => 'Winston Churchill',
                'category' => 'sucesso',
            ],
            [
                'text' => 'Liderar é servir os outros.',
                'author' => 'Ken Blanchard',
                'category' => 'liderança',
            ],
            [
                'text' => 'A motivação é o que te faz começar. O hábito é o que te mantém em movimento.',
                'author' => 'Jim Ryun',
                'category' => 'motivacional',
            ],
            [
                'text' => 'Grandes mentes discutem ideias; mentes medianas discutem eventos; mentes pequenas discutem pessoas.',
                'author' => 'Eleanor Roosevelt',
                'category' => 'inspiracional',
            ],
            [
                'text' => 'O cliente nunca está errado.',
                'author' => 'César Ritz',
                'category' => 'empresarial',
            ],
            [
                'text' => 'A estrada para o sucesso é sempre em construção.',
                'author' => 'Lily Tomlin',
                'category' => 'sucesso',
            ],
            [
                'text' => 'A liderança não é sobre estar no comando. É sobre cuidar daqueles sob sua responsabilidade.',
                'author' => 'Simon Sinek',
                'category' => 'liderança',
            ],
        ];
    }
}