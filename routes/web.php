<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\Auth\RegisterWithPlanController;
use App\Http\Controllers\SubscriptionController;

// API routes for pricing
Route::get('/api/pricing/plans', [PricingController::class, 'getPlans'])->name('api.pricing.plans');
Route::get('/api/pricing/plan/{stripe_price_id}', [PricingController::class, 'getPlan'])->name('api.pricing.plan');

// Development route to create sample products
Route::post('/api/pricing/create-sample-products', [PricingController::class, 'createSampleProducts'])
    ->name('api.pricing.create-sample-products');

Route::get('/', function () {
    return Inertia::render('Landing/Index');
})->name('home');

// Test route
Route::get('/test-route', function () {
    return 'Test route works!';
});

// Registration with plan routes
Route::get('/register-with-plan', [RegisterWithPlanController::class, 'create'])
    ->name('register.with.plan');

Route::post('/register-with-plan', [RegisterWithPlanController::class, 'store'])
    ->name('register.with.plan.store');

// Subscription routes
Route::get('/subscription/success', [SubscriptionController::class, 'success'])
    ->name('subscription.success');

Route::get('/subscription/cancel/{tenant?}', [SubscriptionController::class, 'cancel'])
    ->name('subscription.cancel');

Route::post('/subscription/retry/{tenant}', [SubscriptionController::class, 'retry'])
    ->name('subscription.retry');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();

        // Se é admin, carregar dados globais
        if ($user->isAdmin()) {
            $tenants = \App\Models\Tenant::withCount('users')
                ->orderBy('name')
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'users_count' => $tenant->users_count,
                        'is_active' => $tenant->is_active,
                    ];
                });

            return Inertia::render('dashboard', compact('tenants'));
        }

        // Se é client, carregar com tenant
        if ($user->isClient()) {
            $user->load('tenant');
        }

        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::patch('settings/theme', function () {
        request()->validate([
            'theme' => 'required|in:light,dark,system',
        ]);

        auth()->user()->update([
            'theme' => request('theme'),
        ]);

        return back();
    })->name('settings.theme');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
