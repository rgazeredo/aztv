<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Stripe\Stripe;
use Stripe\Price;
use Stripe\Product;
use Stripe\Customer;
use Stripe\Checkout\Session;

class RegisterWithPlanController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Show the registration form with plan
     */
    public function create(Request $request)
    {
        $planId = $request->get('plan');
        \Log::info('RegisterWithPlan - Starting method', ['plan' => $planId]);

        if (!$planId) {
            \Log::error('No plan ID provided');
            return redirect()->route('home')->with('error', 'Plano não especificado');
        }

        try {
            \Log::info('About to retrieve Stripe price');
            $price = Price::retrieve($planId);
            \Log::info('Stripe price retrieved successfully');

            // Buscar o produto separadamente se não foi expandido
            $product = null;
            if (is_string($price->product)) {
                \Log::info('Retrieving product separately', ['product_id' => $price->product]);
                try {
                    $product = Product::retrieve($price->product);
                    \Log::info('Product retrieved successfully');
                } catch (\Exception $productError) {
                    \Log::error('Error retrieving product', ['error' => $productError->getMessage()]);
                    // Usar dados padrão se não conseguir buscar o produto
                    $product = (object) [
                        'id' => $price->product,
                        'name' => 'Plano Selecionado',
                        'description' => 'Plano de assinatura',
                        'metadata' => []
                    ];
                }
            } else {
                $product = $price->product;
            }

            $features = [];

            if (!empty($product->marketing_features)) {
                foreach ($product->marketing_features as $feature) {
                    $features[] = $feature->name;
                }
            }

            $planData = [
                'id' => $price->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? '',
                'price' => $price->unit_amount / 100,
                'currency' => strtoupper($price->currency),
                'interval' => $price->recurring->interval ?? 'month',
                'features' => $features,
                'metadata' => $product->metadata ?? []
            ];

            return Inertia::render('auth/RegisterWithPlan', [
                'plan' => $planData
            ]);
        } catch (\Exception $e) {
            \Log::error('Exception retrieving price', ['error' => $e->getMessage()]);
            return redirect()->route('home')->with('error', 'Erro ao buscar dados do plano');
        }
    }

    /**
     * Process registration and redirect to Stripe Checkout
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Dados pessoais
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',

            // Dados da empresa (opcionais)
            'company_name' => 'nullable|string|max:255',
            'document' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',

            // Endereço (opcionais)
            'address.cep' => 'nullable|string|max:10',
            'address.street' => 'nullable|string|max:255',
            'address.number' => 'nullable|string|max:10',
            'address.complement' => 'nullable|string|max:255',
            'address.neighborhood' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:2',

            // Plano
            'plan_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Verificar se o plano existe no Stripe
            $price = Price::retrieve($request->plan_id);

            // Buscar o produto separadamente se não foi expandido
            $product = null;
            if (is_string($price->product)) {
                try {
                    $product = Product::retrieve($price->product);
                } catch (\Exception $productError) {
                    \Log::error('Error retrieving product in store', ['error' => $productError->getMessage()]);
                    throw new \Exception('Produto não encontrado');
                }
            } else {
                $product = $price->product;
            }

            if (!$price->active || !$product->active) {
                throw new \Exception('Plano não disponível');
            }

            // Determinar o nome do tenant
            $tenantName = $request->company_name ?: $this->generateTenantName();

            // Criar tenant
            $tenant = Tenant::create([
                'name' => $tenantName,
                'slug' => Str::slug($tenantName),
                'phone' => $request->phone,
                'document' => $request->document,
                'address' => $request->address,
                'plan_metadata' => [
                    'stripe_price_id' => $price->id,
                    'plan_name' => $product->name,
                    'plan_price' => $price->unit_amount / 100,
                    'plan_currency' => $price->currency,
                    'plan_interval' => $price->recurring->interval ?? 'month',
                    'selected_at' => now()->toISOString(),
                    'metadata' => $product->metadata ?? []
                ],
                'settings' => [
                    'features' => $this->getSimplePlanFeatures($product->name),
                    'limits' => $this->getPlanLimits($product->metadata ?? [])
                ],
                'is_active' => false // Será ativado após pagamento
            ]);

            // Criar usuário
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'client',
                'tenant_id' => $tenant->id,
            ]);

            // Criar customer no Stripe
            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'phone' => $tenant->phone,
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                'address' => [
                    'line1' => $request->address['street'] . ', ' . $request->address['number'],
                    'line2' => $request->address['complement'] ?? null,
                    'city' => $request->address['city'],
                    'state' => $request->address['state'],
                    'postal_code' => $request->address['cep'],
                    'country' => 'BR',
                ]
            ]);

            // Atualizar tenant com stripe_id
            $tenant->update(['stripe_id' => $customer->id]);

            // Criar sessão do Checkout
            $checkoutSession = Session::create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price' => $price->id,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'success_url' => route('subscription.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription.cancel', ['tenant' => $tenant->id]),
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                'allow_promotion_codes' => true,
                'billing_address_collection' => 'auto',
                'customer_update' => [
                    'address' => 'auto',
                ]
            ]);

            DB::commit();

            return response()->json([
                'checkout_url' => $checkoutSession->url
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Erro ao processar cadastro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get plan features - simplified version
     */
    private function getSimplePlanFeatures($name)
    {
        return [
            'Dashboard completo',
            'Mensagens ilimitadas',
            'Suporte técnico'
        ];
    }

    /**
     * Get plan features based on product name or metadata
     */
    private function getPlanFeatures($name, $metadata)
    {
        // Se as features estão definidas no metadata do Stripe
        if (isset($metadata['features'])) {
            return json_decode($metadata['features'], true);
        }

        // Features padrão baseadas no nome
        $name = strtolower($name);

        if (str_contains($name, 'básico') || str_contains($name, 'basic')) {
            return [
                '1.000 mensagens/mês',
                'Dashboard básico',
                'Suporte por email'
            ];
        }

        if (str_contains($name, 'profissional') || str_contains($name, 'professional') || str_contains($name, 'pro')) {
            return [
                '5.000 mensagens/mês',
                'Dashboard avançado',
                'API acesso completo',
                'Suporte prioritário'
            ];
        }

        if (str_contains($name, 'empresarial') || str_contains($name, 'enterprise') || str_contains($name, 'business')) {
            return [
                'Conexões ilimitadas',
                '25.000 mensagens/mês',
                'Dashboard empresarial',
                'API completa + Webhooks',
                'Cursos exclusivos',
                'Suporte 24/7'
            ];
        }

        return [];
    }

    /**
     * Get plan limits based on metadata
     */
    private function getPlanLimits($metadata)
    {
        return [
            'max_users' => '100',
        ];
    }

    /**
     * Generate unique tenant name
     */
    private function generateTenantName(): string
    {
        $names = [
            // planetas e luas
            'mercury',
            'venus',
            'mars',
            'jupiter',
            'saturn',
            'uranus',
            'neptune',
            'pluto',
            'titan',
            'europa',
            'ganymede',
            'callisto',
            'io',
            'enceladus',
            'triton',

            // universo
            'galaxy',
            'nebula',
            'supernova',
            'pulsar',
            'quasar',
            'comet',
            'asteroid',
            'meteor',
            'eclipse',
            'orbit',
            'cosmos',
            'starlight',
            'blackhole',
            'singularity',

            // constelações e estrelas
            'orion',
            'andromeda',
            'lyra',
            'cygnus',
            'draco',
            'phoenix',
            'hydra',
            'pegasus',
            'sirius',
            'vega',
            'polaris',
            'altair',
            'betelgeuse',
            'rigel',

            // exploração espacial
            'apollo',
            'gemini',
            'voyager',
            'pioneer',
            'hubble',
            'kepler',
            'artemis',
            'discovery',
            'atlantis',
            'endeavour',
            'challenger',
        ];

        do {
            // sorteia um nome
            $name = $names[array_rand($names)];

            // gera número aleatório
            $number = rand(100, 99999);

            $tenantName = "{$name}-{$number}";

            // verifica se já existe na tabela tenants
            $exists = Tenant::where('name', $tenantName)->exists();
        } while ($exists);

        return $tenantName;
    }
}
