<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class QRCodeController extends Controller
{
    protected QRCodeService $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    public function generate(Request $request, Player $player)
    {
        $this->authorize('view', $player);

        $cacheKey = "qr_code_player_{$player->id}";

        $existingPath = Cache::remember($cacheKey, 3600, function () use ($player) {
            return $this->qrCodeService->getPlayerQRCodePath($player);
        });

        if (!$existingPath) {
            $options = [
                'size' => $request->get('size', 300),
                'margin' => $request->get('margin', 2),
                'primaryColor' => $request->get('primaryColor', '#000000'),
                'backgroundColor' => $request->get('backgroundColor', '#FFFFFF'),
            ];

            if ($request->has('logo') && $request->get('logo')) {
                $options['logo'] = $request->get('logo');
            }

            $path = $this->qrCodeService->savePlayerQRCode($player, $options);
            Cache::put($cacheKey, $path, 3600);
        } else {
            $path = $existingPath;
        }

        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'url' => $url,
            'path' => $path,
            'activation_url' => $player->getActivationUrl(),
        ]);
    }

    public function download(Request $request, Player $player)
    {
        $this->authorize('view', $player);

        $path = $this->qrCodeService->getPlayerQRCodePath($player);

        if (!$path) {
            $options = [
                'size' => $request->get('size', 300),
                'margin' => $request->get('margin', 2),
                'primaryColor' => $request->get('primaryColor', '#000000'),
                'backgroundColor' => $request->get('backgroundColor', '#FFFFFF'),
            ];

            if ($request->has('logo') && $request->get('logo')) {
                $options['logo'] = $request->get('logo');
            }

            $path = $this->qrCodeService->savePlayerQRCode($player, $options);
        }

        $fullPath = Storage::disk('public')->path($path);

        if (!file_exists($fullPath)) {
            abort(404, 'QR Code não encontrado');
        }

        $filename = "qrcode_player_{$player->name}_{$player->id}.png";
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'image/png',
        ]);
    }

    public function regenerate(Request $request, Player $player)
    {
        $this->authorize('update', $player);

        $this->qrCodeService->deletePlayerQRCode($player);

        $cacheKey = "qr_code_player_{$player->id}";
        Cache::forget($cacheKey);

        $options = [
            'size' => $request->get('size', 300),
            'margin' => $request->get('margin', 2),
            'primaryColor' => $request->get('primaryColor', '#000000'),
            'backgroundColor' => $request->get('backgroundColor', '#FFFFFF'),
        ];

        if ($request->has('logo') && $request->get('logo')) {
            $options['logo'] = $request->get('logo');
        }

        $path = $this->qrCodeService->savePlayerQRCode($player, $options);
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'url' => $url,
            'path' => $path,
            'activation_url' => $player->getActivationUrl(),
            'message' => 'QR Code regenerado com sucesso',
        ]);
    }

    public function show(Player $player)
    {
        $this->authorize('view', $player);

        $qrCodeUrl = $this->qrCodeService->getPlayerQRCodeUrl($player);
        $activationUrl = $player->getActivationUrl();

        return response()->json([
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'activation_token' => $player->activation_token,
            ],
            'qr_code_url' => $qrCodeUrl,
            'activation_url' => $activationUrl,
            'has_qr_code' => !is_null($qrCodeUrl),
        ]);
    }
}