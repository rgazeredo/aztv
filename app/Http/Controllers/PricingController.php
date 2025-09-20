<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Price;
use Stripe\Product;

class PricingController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Get pricing plans from Stripe
     */
    public function getPlans()
    {
        try {
            // Buscar todos os preços ativos
            $prices = Price::all([
                'active' => true,
                'expand' => ['data.product'],
                'limit' => 100
            ]);

            $plans = [];

            foreach ($prices->data as $price) {
                $product = $price->product;

                // Pular se não for produto ativo
                if (!$product->active) {
                    continue;
                }

                // Filtrar apenas planos principais (não addons)
                if (isset($product->metadata['role']) && $product->metadata['role'] !== 'plan') {
                    continue;
                }

                // Converter centavos para reais
                $amount = $price->unit_amount / 100;

                // Determinar o tipo de plano baseado no nome ou metadata
                $planType = $this->determinePlanType($product->name, $product->metadata ?? []);

                $features = [];

                if (!empty($product->marketing_features)) {
                    foreach ($product->marketing_features as $feature) {
                        $features[] = $feature->name;
                    }
                }

                $plans[] = [
                    'id' => $price->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description ?? '',
                    'price' => $amount,
                    'currency' => strtoupper($price->currency),
                    'interval' => $price->recurring->interval ?? 'month',
                    'interval_count' => $price->recurring->interval_count ?? 1,
                    'type' => $planType,
                    'features' => $features,
                    'popular' => $planType === 'professional', // Marcar profissional como popular
                    'stripe_price_id' => $price->id,
                    'metadata' => $product->metadata ?? []
                ];
            }

            // Ordenar planos por preço
            usort($plans, function ($a, $b) {
                return $a['price'] <=> $b['price'];
            });

            return response()->json($plans);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao buscar planos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single plan by stripe_price_id
     */
    public function getPlan($stripe_price_id)
    {
        try {
            // Buscar o preço no Stripe
            $price = Price::retrieve($stripe_price_id);

            if (!$price->active) {
                return response()->json([
                    'error' => 'Plano não está ativo'
                ], 404);
            }

            // Buscar o produto separadamente se não foi expandido
            $product = null;
            if (is_string($price->product)) {
                $product = Product::retrieve($price->product);
            } else {
                $product = $price->product;
            }

            if (!$product->active) {
                return response()->json([
                    'error' => 'Produto não está ativo'
                ], 404);
            }

            // Determinar tipo de plano
            $planType = $this->determinePlanType($product->name, $product->metadata ?? []);

            // Montar features
            $features = [];
            if (!empty($product->marketing_features)) {
                foreach ($product->marketing_features as $feature) {
                    $features[] = $feature->name;
                }
            }

            // Montar dados do plano
            $planData = [
                'id' => $planType,
                'name' => $product->name,
                'description' => $product->description ?? '',
                'price' => $price->unit_amount / 100,
                'currency' => strtoupper($price->currency),
                'interval' => $price->recurring->interval ?? 'month',
                'features' => $features,
                'popular' => $planType === 'professional',
                'stripe_price_id' => $price->id,
                'metadata' => $product->metadata ?? []
            ];

            return response()->json($planData);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return response()->json([
                'error' => 'Plano não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao buscar plano: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determinar o tipo de plano baseado no nome ou metadata
     */
    private function determinePlanType($name, $metadata)
    {
        $name = strtolower($name);

        if (isset($metadata['plan_type'])) {
            return $metadata['plan_type'];
        }

        if (str_contains($name, 'básico') || str_contains($name, 'basic')) {
            return 'basic';
        }

        if (str_contains($name, 'profissional') || str_contains($name, 'professional') || str_contains($name, 'pro')) {
            return 'professional';
        }

        if (str_contains($name, 'empresarial') || str_contains($name, 'enterprise') || str_contains($name, 'business')) {
            return 'enterprise';
        }

        return 'basic'; // Default
    }

    /**
     * Criar produtos de exemplo no Stripe (para desenvolvimento)
     */
    public function createSampleProducts()
    {
        if (app()->environment('production')) {
            return response()->json(['error' => 'Não é possível criar produtos em produção'], 403);
        }

        try {
            $products = [
                [
                    'name' => 'Plano Básico',
                    'description' => 'Ideal para pequenos negócios',
                    'price' => 2990, // R$ 29,90 em centavos
                    'type' => 'basic'
                ],
                [
                    'name' => 'Plano Profissional',
                    'description' => 'Para empresas em crescimento',
                    'price' => 5990, // R$ 59,90 em centavos
                    'type' => 'professional'
                ],
                [
                    'name' => 'Plano Empresarial',
                    'description' => 'Para grandes volumes',
                    'price' => 14990, // R$ 149,90 em centavos
                    'type' => 'enterprise'
                ]
            ];

            $createdProducts = [];

            foreach ($products as $productData) {
                // Criar produto
                $product = Product::create([
                    'name' => $productData['name'],
                    'description' => $productData['description'],
                    'metadata' => [
                        'plan_type' => $productData['type']
                    ]
                ]);

                // Criar preço
                $price = Price::create([
                    'unit_amount' => $productData['price'],
                    'currency' => 'brl',
                    'recurring' => [
                        'interval' => 'month',
                    ],
                    'product' => $product->id,
                ]);

                $createdProducts[] = [
                    'product' => $product,
                    'price' => $price
                ];
            }

            return response()->json([
                'message' => 'Produtos criados com sucesso',
                'products' => $createdProducts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao criar produtos: ' . $e->getMessage()
            ], 500);
        }
    }
}
