<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PlayerApiController;
use App\Http\Controllers\Api\ContentApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public API endpoints for player authentication
Route::post('/player/authenticate', [PlayerApiController::class, 'authenticate'])
    ->name('api.player.authenticate');

Route::get('/apk/check-update', [PlayerApiController::class, 'checkUpdate'])
    ->name('api.apk.check-update');

// Protected API endpoints for authenticated players
Route::middleware('player.auth')->prefix('player')->name('api.player.')->group(function () {
    // Player Status and Communication
    Route::post('/heartbeat', [PlayerApiController::class, 'heartbeat'])
        ->name('heartbeat');

    Route::get('/commands', [PlayerApiController::class, 'getCommands'])
        ->name('commands');

    Route::post('/commands/confirm', [PlayerApiController::class, 'confirmCommand'])
        ->name('commands.confirm');

    Route::put('/settings', [PlayerApiController::class, 'updateSettings'])
        ->name('settings.update');

    // Content Synchronization
    Route::get('/playlists', [PlayerApiController::class, 'getPlaylists'])
        ->name('playlists');

    Route::get('/content-modules', [PlayerApiController::class, 'getContentModules'])
        ->name('content-modules');

    // Logging
    Route::post('/log/media-played', [PlayerApiController::class, 'logMediaPlayed'])
        ->name('log.media-played');

    Route::post('/log/error', [PlayerApiController::class, 'logError'])
        ->name('log.error');

    // App Updates
    Route::get('/update/check', [PlayerApiController::class, 'checkUpdate'])
        ->name('update.check');

    Route::get('/update/download', [PlayerApiController::class, 'downloadUpdate'])
        ->name('update.download');
});

// Content API endpoints for dynamic content
Route::middleware('player.auth')->prefix('content')->name('api.content.')->group(function () {
    // Get all enabled content modules
    Route::get('/', [ContentApiController::class, 'getContent'])
        ->name('index');

    // Specific content types
    Route::get('/weather', [ContentApiController::class, 'getWeather'])
        ->name('weather');

    Route::get('/currency', [ContentApiController::class, 'getCurrency'])
        ->name('currency');

    Route::get('/quote', [ContentApiController::class, 'getQuote'])
        ->name('quote');

    Route::get('/health-tip', [ContentApiController::class, 'getHealthTip'])
        ->name('health-tip');

    Route::get('/price-table', [ContentApiController::class, 'getPriceTable'])
        ->name('price-table');
});

// Admin API endpoints for system monitoring
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('api.admin.')->group(function () {
    // System monitoring
    Route::get('/system-health', function () {
        $adminController = new \App\Http\Controllers\AdminController();
        return response()->json($adminController->systemHealth());
    })->name('system-health');

    // Analytics
    Route::get('/analytics', function (Request $request) {
        $adminController = new \App\Http\Controllers\AdminController();
        return response()->json($adminController->analytics($request));
    })->name('analytics');

    // Quick stats
    Route::get('/stats', function () {
        return response()->json([
            'total_tenants' => \App\Models\Tenant::count(),
            'active_tenants' => \App\Models\Tenant::active()->count(),
            'total_players' => \App\Models\Player::count(),
            'online_players' => \App\Models\Player::online()->count(),
            'total_media_files' => \App\Models\MediaFile::count(),
            'apk_downloads' => \App\Models\ApkVersion::sum('download_count'),
        ]);
    })->name('stats');
});

// Tenant-specific API endpoints
Route::middleware(['auth:sanctum', 'tenant'])->prefix('tenant')->name('api.tenant.')->group(function () {
    // Player management
    Route::get('/players', function (Request $request) {
        $tenantId = auth()->user()->tenant_id;
        return \App\Models\Player::forTenant($tenantId)
            ->with(['playlists'])
            ->get();
    })->name('players');

    // Media management
    Route::get('/media', function (Request $request) {
        $tenantId = auth()->user()->tenant_id;
        return \App\Models\MediaFile::forTenant($tenantId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    })->name('media');

    // Playlist management
    Route::get('/playlists', function (Request $request) {
        $tenantId = auth()->user()->tenant_id;
        return \App\Models\Playlist::forTenant($tenantId)
            ->withCount('items')
            ->get();
    })->name('playlists');

    // Content modules
    Route::get('/content-modules', function (Request $request) {
        $tenantId = auth()->user()->tenant_id;
        return \App\Models\ContentModule::forTenant($tenantId)->get();
    })->name('content-modules');

    // Dashboard stats
    Route::get('/stats', function () {
        $tenantId = auth()->user()->tenant_id;

        return response()->json([
            'total_players' => \App\Models\Player::forTenant($tenantId)->count(),
            'online_players' => \App\Models\Player::forTenant($tenantId)->online()->count(),
            'total_media_files' => \App\Models\MediaFile::forTenant($tenantId)->count(),
            'total_playlists' => \App\Models\Playlist::forTenant($tenantId)->count(),
            'storage_used' => \App\Models\MediaFile::forTenant($tenantId)->sum('size'),
            'enabled_modules' => \App\Models\ContentModule::forTenant($tenantId)->enabled()->count(),
        ]);
    })->name('stats');
});

// Webhook endpoints
Route::prefix('webhooks')->name('api.webhooks.')->group(function () {
    // Stripe webhooks
    Route::post('/stripe', function (Request $request) {
        // Handle Stripe webhook events
        return response()->json(['status' => 'received']);
    })->name('stripe');

    // Generic webhook for external integrations
    Route::post('/external/{token}', function (Request $request, $token) {
        // Handle external webhook events
        return response()->json(['status' => 'received']);
    })->name('external');
});