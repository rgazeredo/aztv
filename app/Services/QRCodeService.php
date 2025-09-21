<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

class QRCodeService
{
    public function generatePlayerActivationQR(Player $player, array $options = []): string
    {
        $activationUrl = $player->getActivationUrl();

        $logo = $options['logo'] ?? null;
        $primaryColor = $options['primaryColor'] ?? '#000000';
        $backgroundColor = $options['backgroundColor'] ?? '#FFFFFF';
        $size = $options['size'] ?? 300;
        $margin = $options['margin'] ?? 2;

        return $this->generateQRCode(
            $activationUrl,
            $logo,
            $primaryColor,
            $backgroundColor,
            $size,
            $margin
        );
    }

    public function generateQRCode(
        string $data,
        ?string $logo = null,
        string $primaryColor = '#000000',
        string $backgroundColor = '#FFFFFF',
        int $size = 300,
        int $margin = 2
    ): string {
        $qrCode = QrCode::format('png')
            ->size($size)
            ->margin($margin)
            ->color(
                hexdec(substr($primaryColor, 1, 2)),
                hexdec(substr($primaryColor, 3, 2)),
                hexdec(substr($primaryColor, 5, 2))
            )
            ->backgroundColor(
                hexdec(substr($backgroundColor, 1, 2)),
                hexdec(substr($backgroundColor, 3, 2)),
                hexdec(substr($backgroundColor, 5, 2))
            );

        if ($logo && Storage::disk('public')->exists($logo)) {
            $logoPath = Storage::disk('public')->path($logo);
            $qrCode->merge($logoPath, 0.3, true);
        }

        return $qrCode->generate($data);
    }

    public function savePlayerQRCode(Player $player, array $options = []): string
    {
        $qrCodeContent = $this->generatePlayerActivationQR($player, $options);

        $tenantId = $player->tenant_id;
        $playerId = $player->id;
        $filename = "qr-codes/{$tenantId}/{$playerId}.png";

        Storage::disk('public')->put($filename, $qrCodeContent);

        return $filename;
    }

    public function getPlayerQRCodePath(Player $player): ?string
    {
        $tenantId = $player->tenant_id;
        $playerId = $player->id;
        $filename = "qr-codes/{$tenantId}/{$playerId}.png";

        if (Storage::disk('public')->exists($filename)) {
            return $filename;
        }

        return null;
    }

    public function getPlayerQRCodeUrl(Player $player): ?string
    {
        $path = $this->getPlayerQRCodePath($player);

        if ($path) {
            return Storage::disk('public')->url($path);
        }

        return null;
    }

    public function deletePlayerQRCode(Player $player): bool
    {
        $path = $this->getPlayerQRCodePath($player);

        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }
}