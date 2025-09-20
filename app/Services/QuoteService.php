<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class QuoteService
{
    private array $localQuotes = [
        'motivational' => [
            [
                'text' => 'O sucesso é a soma de pequenos esforços repetidos dia após dia.',
                'author' => 'Robert Collier',
            ],
            [
                'text' => 'A única forma de fazer um trabalho excelente é amar o que você faz.',
                'author' => 'Steve Jobs',
            ],
            [
                'text' => 'O futuro pertence àqueles que acreditam na beleza de seus sonhos.',
                'author' => 'Eleanor Roosevelt',
            ],
            [
                'text' => 'Não é o mais forte que sobrevive, nem o mais inteligente, mas o que melhor se adapta às mudanças.',
                'author' => 'Charles Darwin',
            ],
            [
                'text' => 'A persistência é o caminho do êxito.',
                'author' => 'Charles Chaplin',
            ],
        ],
        'business' => [
            [
                'text' => 'Seus clientes mais insatisfeitos são sua maior fonte de aprendizado.',
                'author' => 'Bill Gates',
            ],
            [
                'text' => 'Inovação distingue entre um líder e um seguidor.',
                'author' => 'Steve Jobs',
            ],
            [
                'text' => 'O cliente é a parte mais importante da linha de produção.',
                'author' => 'W. Edwards Deming',
            ],
            [
                'text' => 'Uma empresa é conhecida pelos homens que mantém.',
                'author' => 'James Merrill',
            ],
            [
                'text' => 'Qualidade não é um ato, é um hábito.',
                'author' => 'Aristóteles',
            ],
        ],
        'life' => [
            [
                'text' => 'A vida é o que acontece enquanto você está ocupado fazendo outros planos.',
                'author' => 'John Lennon',
            ],
            [
                'text' => 'Seja você mesmo; todos os outros já existem.',
                'author' => 'Oscar Wilde',
            ],
            [
                'text' => 'A vida é realmente simples, mas insistimos em torná-la complicada.',
                'author' => 'Confúcio',
            ],
            [
                'text' => 'Em vinte anos você ficará mais decepcionado com as coisas que não fez do que com aquelas que fez.',
                'author' => 'Mark Twain',
            ],
            [
                'text' => 'A felicidade não é algo pronto. Ela vem de suas próprias ações.',
                'author' => 'Dalai Lama',
            ],
        ],
        'success' => [
            [
                'text' => 'O sucesso não é final, o fracasso não é fatal: é a coragem de continuar que conta.',
                'author' => 'Winston Churchill',
            ],
            [
                'text' => 'Sucesso é ir de fracasso em fracasso sem perder o entusiasmo.',
                'author' => 'Winston Churchill',
            ],
            [
                'text' => 'O único lugar onde o sucesso vem antes do trabalho é no dicionário.',
                'author' => 'Vidal Sassoon',
            ],
            [
                'text' => 'Não tenha medo de desistir do bom para perseguir o ótimo.',
                'author' => 'John D. Rockefeller',
            ],
            [
                'text' => 'O sucesso é uma questão de pendências, não de inteligência.',
                'author' => 'Thomas Edison',
            ],
        ],
    ];

    public function getQuote(string $category = 'motivational'): array
    {
        $cacheKey = "quote_{$category}";

        return Cache::remember($cacheKey, 3600, function () use ($category) {
            // Try to get from external API first
            $externalQuote = $this->getExternalQuote($category);

            if ($externalQuote) {
                return $externalQuote;
            }

            // Fallback to local quotes
            return $this->getLocalQuote($category);
        });
    }

    public function getRandomQuote(): array
    {
        $categories = array_keys($this->localQuotes);
        $randomCategory = $categories[array_rand($categories)];

        return $this->getQuote($randomCategory);
    }

    public function getDailyQuote(): array
    {
        $cacheKey = 'daily_quote_' . date('Y-m-d');

        return Cache::remember($cacheKey, 86400, function () {
            return $this->getRandomQuote();
        });
    }

    public function getQuotesByCategory(string $category, int $limit = 5): array
    {
        $quotes = $this->localQuotes[$category] ?? $this->localQuotes['motivational'];

        shuffle($quotes);

        return array_slice($quotes, 0, $limit);
    }

    public function searchQuotes(string $search): array
    {
        $results = [];

        foreach ($this->localQuotes as $category => $quotes) {
            foreach ($quotes as $quote) {
                if (
                    stripos($quote['text'], $search) !== false ||
                    stripos($quote['author'], $search) !== false
                ) {
                    $quote['category'] = $category;
                    $results[] = $quote;
                }
            }
        }

        return $results;
    }

    public function getAvailableCategories(): array
    {
        return [
            'motivational' => [
                'name' => 'Motivacional',
                'description' => 'Frases para inspirar e motivar',
                'icon' => '💪',
            ],
            'business' => [
                'name' => 'Negócios',
                'description' => 'Frases sobre empreendedorismo e liderança',
                'icon' => '💼',
            ],
            'life' => [
                'name' => 'Vida',
                'description' => 'Reflexões sobre a vida',
                'icon' => '🌟',
            ],
            'success' => [
                'name' => 'Sucesso',
                'description' => 'Frases sobre conquistas e realizações',
                'icon' => '🏆',
            ],
        ];
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::timeout(5)->get('https://api.quotable.io/random');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getExternalQuote(string $category): ?array
    {
        try {
            // Map categories to external API tags
            $tagMap = [
                'motivational' => 'motivational',
                'business' => 'business,entrepreneurship',
                'life' => 'life,wisdom',
                'success' => 'success,achievement',
            ];

            $tags = $tagMap[$category] ?? 'inspirational';

            $response = Http::timeout(10)->get('https://api.quotable.io/random', [
                'tags' => $tags,
                'maxLength' => 150,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'text' => $data['content'],
                    'author' => $data['author'],
                    'category' => $category,
                    'source' => 'external',
                    'background_color' => $this->getRandomColor(),
                    'text_color' => '#ffffff',
                ];
            }
        } catch (\Exception $e) {
            // Fall through to local quotes
        }

        return null;
    }

    private function getLocalQuote(string $category): array
    {
        $quotes = $this->localQuotes[$category] ?? $this->localQuotes['motivational'];
        $quote = $quotes[array_rand($quotes)];

        return array_merge($quote, [
            'category' => $category,
            'source' => 'local',
            'background_color' => $this->getRandomColor(),
            'text_color' => '#ffffff',
        ]);
    }

    private function getRandomColor(): string
    {
        $colors = [
            '#3498db', // Blue
            '#e74c3c', // Red
            '#2ecc71', // Green
            '#f39c12', // Orange
            '#9b59b6', // Purple
            '#1abc9c', // Turquoise
            '#34495e', // Dark Blue
            '#e67e22', // Carrot
            '#27ae60', // Nephritis
            '#8e44ad', // Wisteria
        ];

        return $colors[array_rand($colors)];
    }
}