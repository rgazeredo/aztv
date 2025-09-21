<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\Auth\RegisterWithPlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TenantManagementController;
use App\Http\Controllers\ApkManagementController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ContentModuleController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\PlaylistScheduleController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\PlayerLogController;

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

// Public activation routes (no auth required)
Route::get('/activation/{token}', [ActivationController::class, 'show'])->name('activation.show');
Route::get('/activation/{token}/download', [ActivationController::class, 'download'])->name('activation.download');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();

        // Se é admin, redirecionar para dashboard admin
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // Se é client, usar DashboardController
        if ($user->isClient()) {
            return app(DashboardController::class)->index(request());
        }

        return Inertia::render('dashboard');
    })->name('dashboard');

    // Dashboard metrics endpoint for AJAX calls
    Route::get('dashboard/metrics', [DashboardController::class, 'getMetrics'])
        ->name('dashboard.metrics')
        ->middleware('tenant');

    // Admin Routes
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('system-health', [AdminController::class, 'systemHealth'])->name('system-health');
        Route::get('analytics', [AdminController::class, 'analytics'])->name('analytics');

        // Tenant Management
        Route::resource('tenants', TenantManagementController::class);
        Route::post('tenants/{tenant}/toggle-status', [TenantManagementController::class, 'toggleStatus'])->name('tenants.toggle-status');
        Route::post('tenants/{tenant}/impersonate', [TenantManagementController::class, 'impersonate'])->name('tenants.impersonate');
        Route::post('tenants/bulk-action', [TenantManagementController::class, 'bulkAction'])->name('tenants.bulk-action');
        Route::get('tenants/{tenant}/analytics', [TenantManagementController::class, 'analytics'])->name('tenants.analytics');

        // APK Management
        Route::resource('apk', ApkManagementController::class)->except(['edit', 'update']);
        Route::put('apk/{apkVersion}', [ApkManagementController::class, 'update'])->name('apk.update');
        Route::get('apk/{apkVersion}/edit', [ApkManagementController::class, 'edit'])->name('apk.edit');
        Route::post('apk/{apkVersion}/activate', [ApkManagementController::class, 'activate'])->name('apk.activate');
        Route::post('apk/{apkVersion}/deactivate', [ApkManagementController::class, 'deactivate'])->name('apk.deactivate');
        Route::get('apk/{apkVersion}/download', [ApkManagementController::class, 'download'])->name('apk.download');
        Route::get('apk-download/latest', [ApkManagementController::class, 'downloadLatest'])->name('apk.download-latest');
        Route::get('apk-download/active', [ApkManagementController::class, 'downloadActive'])->name('apk.download-active');
        Route::get('apk/{apkVersion}/qr-code', [ApkManagementController::class, 'qrCode'])->name('apk.qr-code');
        Route::post('apk/bulk-action', [ApkManagementController::class, 'bulkAction'])->name('apk.bulk-action');
        Route::get('apk-analytics', [ApkManagementController::class, 'analytics'])->name('apk.analytics');
        Route::post('apk/{apkVersion}/force-update', [ApkManagementController::class, 'forceUpdate'])->name('apk.force-update');

        Route::post('stop-impersonating', [TenantManagementController::class, 'stopImpersonating'])->name('stop-impersonating');
    });

    // Client Routes (Tenant-specific)
    Route::middleware('tenant')->group(function () {
        // Players
        Route::resource('players', PlayerController::class);
        Route::post('players/{player}/regenerate-token', [PlayerController::class, 'regenerateToken'])->name('players.regenerate-token');
        Route::post('players/{player}/restart', [PlayerController::class, 'restart'])->name('players.restart');
        Route::post('players/{player}/send-command', [PlayerController::class, 'sendCommand'])->name('players.send-command');
        Route::post('players/bulk-action', [PlayerController::class, 'bulkAction'])->name('players.bulk-action');
        Route::get('players/{player}/logs', [PlayerController::class, 'logs'])->name('players.logs');
        Route::get('players/{player}/analytics', [PlayerController::class, 'analytics'])->name('players.analytics');

        // QR Code routes
        Route::get('qr-code/player/{player}/generate', [QRCodeController::class, 'generate'])->name('qr-code.player.generate');
        Route::get('qr-code/player/{player}/download', [QRCodeController::class, 'download'])->name('qr-code.player.download');
        Route::get('qr-code/player/{player}/info', [QRCodeController::class, 'info'])->name('qr-code.player.info');
        Route::post('qr-code/player/{player}/regenerate', [QRCodeController::class, 'regenerate'])->name('qr-code.player.regenerate');
        Route::delete('qr-code/player/{player}', [QRCodeController::class, 'delete'])->name('qr-code.player.delete');
        Route::post('qr-code/bulk-generate', [QRCodeController::class, 'bulkGenerate'])->name('qr-code.bulk-generate');

        // Media
        Route::resource('media', MediaController::class);
        Route::get('media/{mediaFile}/download', [MediaController::class, 'download'])->name('media.download');
        Route::get('media/{mediaFile}/preview', [MediaController::class, 'preview'])->name('media.preview');
        Route::post('media/bulk-action', [MediaController::class, 'bulkAction'])->name('media.bulk-action');
        Route::get('media-folders', [MediaController::class, 'folders'])->name('media.folders');
        Route::post('media-folders', [MediaController::class, 'createFolder'])->name('media.create-folder');
        Route::delete('media-folders', [MediaController::class, 'deleteFolder'])->name('media.delete-folder');
        Route::get('media-analytics', [MediaController::class, 'analytics'])->name('media.analytics');

        // Playlists
        Route::resource('playlists', PlaylistController::class);
        Route::post('playlists/{playlist}/add-media', [PlaylistController::class, 'addMedia'])->name('playlists.add-media');
        Route::delete('playlists/{playlist}/media/{mediaFile}', [PlaylistController::class, 'removeMedia'])->name('playlists.remove-media');
        Route::post('playlists/{playlist}/reorder-items', [PlaylistController::class, 'reorderItems'])->name('playlists.reorder-items');
        Route::put('playlists/{playlist}/items/{item}/display-time', [PlaylistController::class, 'updateItemDisplayTime'])->name('playlists.update-item-display-time');
        Route::post('playlists/{playlist}/duplicate', [PlaylistController::class, 'duplicate'])->name('playlists.duplicate');
        Route::post('playlists/{playlist}/assign-players', [PlaylistController::class, 'assignToPlayers'])->name('playlists.assign-players');
        Route::delete('playlists/{playlist}/players/{player}', [PlaylistController::class, 'unassignFromPlayer'])->name('playlists.unassign-player');
        Route::post('playlists/{playlist}/mark-default', [PlaylistController::class, 'markAsDefault'])->name('playlists.mark-default');
        Route::get('playlists/{playlist}/available-media', [PlaylistController::class, 'availableMedia'])->name('playlists.available-media');
        Route::get('playlists/{playlist}/available-players', [PlaylistController::class, 'availablePlayers'])->name('playlists.available-players');
        Route::post('playlists/bulk-action', [PlaylistController::class, 'bulkAction'])->name('playlists.bulk-action');
        Route::get('playlists/{playlist}/analytics', [PlaylistController::class, 'analytics'])->name('playlists.analytics');

        // Playlist Schedules
        Route::resource('playlist-schedules', PlaylistScheduleController::class);
        Route::post('playlist-schedules/{playlistSchedule}/toggle', [PlaylistScheduleController::class, 'toggle'])->name('playlist-schedules.toggle');
        Route::post('playlist-schedules/{playlistSchedule}/duplicate', [PlaylistScheduleController::class, 'duplicate'])->name('playlist-schedules.duplicate');
        Route::post('playlist-schedules/preview', [PlaylistScheduleController::class, 'preview'])->name('playlist-schedules.preview');
        Route::post('playlist-schedules/bulk-action', [PlaylistScheduleController::class, 'bulkAction'])->name('playlist-schedules.bulk-action');

        // Content Modules
        Route::resource('content-modules', ContentModuleController::class);
        Route::post('content-modules/{contentModule}/toggle', [ContentModuleController::class, 'toggle'])->name('content-modules.toggle');
        Route::get('content-modules/{contentModule}/test-connection', [ContentModuleController::class, 'testConnection'])->name('content-modules.test-connection');
        Route::get('content-modules/{contentModule}/preview', [ContentModuleController::class, 'preview'])->name('content-modules.preview');
        Route::post('content-modules/bulk-toggle', [ContentModuleController::class, 'bulkToggle'])->name('content-modules.bulk-toggle');
        Route::get('content-modules-content', [ContentModuleController::class, 'getContent'])->name('content-modules.get-content');

        // File Upload
        Route::middleware('storage.quota')->group(function () {
            Route::post('upload/multiple', [\App\Http\Controllers\FileUploadController::class, 'upload'])->name('upload.multiple');
            Route::post('upload/single', [\App\Http\Controllers\FileUploadController::class, 'uploadSingle'])->name('upload.single');
        });

        Route::get('upload/info', [\App\Http\Controllers\FileUploadController::class, 'getUploadInfo'])->name('upload.info');
        Route::post('upload/validate', [\App\Http\Controllers\FileUploadController::class, 'validateUpload'])->name('upload.validate');

        // Activation Token Management
        Route::resource('activation', ActivationController::class)->only(['index', 'store']);
        Route::delete('activation/{token}/revoke', [ActivationController::class, 'revoke'])->name('activation.revoke');
        Route::post('activation/{token}/regenerate-qr', [ActivationController::class, 'regenerateQR'])->name('activation.regenerate-qr');

        // Activity Logs
        Route::resource('activity-logs', ActivityLogController::class)->only(['index', 'show']);
        Route::get('api/activity-logs', [ActivityLogController::class, 'api'])->name('activity-logs.api');

        // Player Logs
        Route::resource('player-logs', PlayerLogController::class)->only(['index', 'show']);
        Route::get('player-logs/export', [PlayerLogController::class, 'export'])->name('player-logs.export');
        Route::get('player-logs/dashboard', [PlayerLogController::class, 'dashboard'])->name('player-logs.dashboard');

        // Alert Rules
        Route::resource('alerts', \App\Http\Controllers\AlertController::class);
        Route::post('alerts/{alertRule}/toggle', [\App\Http\Controllers\AlertController::class, 'toggle'])->name('alerts.toggle');
        Route::post('alerts/{alertRule}/test', [\App\Http\Controllers\AlertController::class, 'test'])->name('alerts.test');
    });

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
