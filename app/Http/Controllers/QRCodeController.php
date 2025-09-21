<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Exception;

class QRCodeController extends Controller
{
    private QRCodeService $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Generate QR code for player activation
     */
    public function generate(Request $request, Player $player)
    {
        // Authorize access to player
        Gate::authorize('view', $player);

        try {
            $cacheKey = "qr_code_player_{$player->id}_{$player->updated_at->timestamp}";

            // Check cache first (1 hour cache)
            $qrCodeUrl = Cache::remember($cacheKey, 3600, function () use ($player, $request) {
                $options = $this->getQRCodeOptions($request);
                return $this->qrCodeService->savePlayerQRCode($player, $options);
            });

            return response()->json([
                'success' => true,
                'qr_code_url' => $qrCodeUrl,
                'player_id' => $player->id,
                'activation_url' => $this->getPlayerActivationUrl($player),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate QR code',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download QR code file directly
     */
    public function download(Player $player)
    {
        // Authorize access to player
        Gate::authorize('view', $player);

        try {
            $qrCodeUrl = $this->qrCodeService->getPlayerQRCodeUrl($player);

            if (!$qrCodeUrl) {
                // Generate QR code if it doesn't exist
                $qrCodeUrl = $this->qrCodeService->savePlayerQRCode($player);
            }

            // Get file path for download
            $qrCodePath = $this->qrCodeService->getPlayerQRCodePath($player);
            $filePath = storage_path('app/public/' . $qrCodePath);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'QR code file not found',
                ], 404);
            }

            $fileName = "player_{$player->id}_qr_code.png";

            return response()->download($filePath, $fileName, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to download QR code',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get QR code info without generating
     */
    public function info(Player $player)
    {
        // Authorize access to player
        Gate::authorize('view', $player);

        $qrCodeUrl = $this->qrCodeService->getPlayerQRCodeUrl($player);
        $hasQRCode = $this->qrCodeService->playerHasQRCode($player);
        $fileSize = $this->qrCodeService->getQRCodeSize($player);

        return response()->json([
            'success' => true,
            'player_id' => $player->id,
            'has_qr_code' => $hasQRCode,
            'qr_code_url' => $qrCodeUrl,
            'file_size' => $fileSize,
            'activation_url' => $this->getPlayerActivationUrl($player),
        ]);
    }

    /**
     * Regenerate QR code for player
     */
    public function regenerate(Request $request, Player $player)
    {
        // Authorize access to player
        Gate::authorize('update', $player);

        try {
            // Delete existing QR code
            $this->qrCodeService->deletePlayerQRCode($player);

            // Clear cache
            $cacheKey = "qr_code_player_{$player->id}_{$player->updated_at->timestamp}";
            Cache::forget($cacheKey);

            // Generate new QR code
            $options = $this->getQRCodeOptions($request);
            $qrCodeUrl = $this->qrCodeService->savePlayerQRCode($player, $options);

            return response()->json([
                'success' => true,
                'qr_code_url' => $qrCodeUrl,
                'player_id' => $player->id,
                'message' => 'QR code regenerated successfully',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to regenerate QR code',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete QR code for player
     */
    public function delete(Player $player)
    {
        // Authorize access to player
        Gate::authorize('update', $player);

        try {
            $deleted = $this->qrCodeService->deletePlayerQRCode($player);

            // Clear cache
            $cacheKey = "qr_code_player_{$player->id}_{$player->updated_at->timestamp}";
            Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'message' => 'QR code deleted successfully',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete QR code',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk generate QR codes for multiple players
     */
    public function bulkGenerate(Request $request)
    {
        $request->validate([
            'player_ids' => 'required|array|min:1',
            'player_ids.*' => 'required|integer|exists:players,id',
        ]);

        $playerIds = $request->input('player_ids');
        $options = $this->getQRCodeOptions($request);
        $results = [];

        foreach ($playerIds as $playerId) {
            $player = Player::find($playerId);

            // Check authorization
            if (!Gate::allows('view', $player)) {
                $results[] = [
                    'player_id' => $playerId,
                    'success' => false,
                    'error' => 'Unauthorized access to player',
                ];
                continue;
            }

            try {
                $qrCodeUrl = $this->qrCodeService->savePlayerQRCode($player, $options);
                $results[] = [
                    'player_id' => $playerId,
                    'success' => true,
                    'qr_code_url' => $qrCodeUrl,
                ];
            } catch (Exception $e) {
                $results[] = [
                    'player_id' => $playerId,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'total_processed' => count($results),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
        ]);
    }

    /**
     * Get QR code options from request
     */
    private function getQRCodeOptions(Request $request): array
    {
        return [
            'size' => $request->input('size', 400),
            'margin' => $request->input('margin', 2),
            'format' => $request->input('format', 'png'),
            'errorCorrectionLevel' => $request->input('error_correction', 'M'),
        ];
    }

    /**
     * Get player activation URL
     */
    private function getPlayerActivationUrl(Player $player): string
    {
        return config('app.url') . "/api/player/activate?token={$player->activation_token}";
    }
}