<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Exception;
use Illuminate\Support\Facades\Log;

class QRCodeService
{
    private string $storagePath = 'qr-codes';

    /**
     * Generate QR code for player activation
     */
    public function generatePlayerActivationQR(Player $player, array $options = []): string
    {
        $activationUrl = $this->getPlayerActivationUrl($player);

        $qrOptions = array_merge([
            'size' => 400,
            'margin' => 2,
            'format' => 'png',
            'errorCorrectionLevel' => 'M', // L, M, Q, H
        ], $options);

        // Get tenant-specific styling
        $tenant = $player->tenant;
        $primaryColor = $tenant->settings['primary_color'] ?? '#000000';
        $backgroundColor = $tenant->settings['background_color'] ?? '#FFFFFF';

        return $this->generateQRCode(
            $activationUrl,
            $qrOptions,
            $primaryColor,
            $backgroundColor,
            $this->getPlayerQRCodePath($player)
        );
    }

    /**
     * Generate QR code with custom styling
     */
    public function generateQRCode(
        string $data,
        array $options = [],
        string $primaryColor = '#000000',
        string $backgroundColor = '#FFFFFF',
        ?string $savePath = null
    ): string {
        try {
            // Generate QR code using SVG format to avoid ImageMagick dependency
            $format = $options['format'] ?? 'svg';

            if ($format === 'png') {
                // For PNG, try to use SVG first and convert if needed
                $qrCodeData = QrCode::format('svg')
                    ->size($options['size'] ?? 400)
                    ->margin($options['margin'] ?? 2)
                    ->errorCorrection($options['errorCorrectionLevel'] ?? 'M')
                    ->generate($data);
            } else {
                $qrCodeData = QrCode::format($format)
                    ->size($options['size'] ?? 400)
                    ->margin($options['margin'] ?? 2)
                    ->errorCorrection($options['errorCorrectionLevel'] ?? 'M')
                    ->generate($data);
            }

            // Save to storage if path provided
            if ($savePath) {
                Storage::disk('public')->put($savePath, $qrCodeData);
                return Storage::disk('public')->url($savePath);
            }

            // Return base64 data URL
            return 'data:image/png;base64,' . base64_encode($qrCodeData);

        } catch (Exception $e) {
            Log::error('Failed to generate QR code: ' . $e->getMessage(), [
                'data' => $data,
                'options' => $options,
                'save_path' => $savePath,
            ]);

            throw new Exception('Failed to generate QR code: ' . $e->getMessage());
        }
    }

    /**
     * Save player QR code to storage
     */
    public function savePlayerQRCode(Player $player, array $options = []): string
    {
        $qrCodePath = $this->getPlayerQRCodePath($player);

        // Ensure directory exists
        $directory = dirname($qrCodePath);
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        return $this->generatePlayerActivationQR($player, $options);
    }

    /**
     * Get player QR code URL if exists
     */
    public function getPlayerQRCodeUrl(Player $player): ?string
    {
        $qrCodePath = $this->getPlayerQRCodePath($player);

        if (Storage::disk('public')->exists($qrCodePath)) {
            return Storage::disk('public')->url($qrCodePath);
        }

        return null;
    }

    /**
     * Get player QR code file path
     */
    public function getPlayerQRCodePath(Player $player): string
    {
        return "{$this->storagePath}/{$player->tenant_id}/{$player->id}/activation.svg";
    }

    /**
     * Delete player QR code
     */
    public function deletePlayerQRCode(Player $player): bool
    {
        $qrCodePath = $this->getPlayerQRCodePath($player);

        if (Storage::disk('public')->exists($qrCodePath)) {
            return Storage::disk('public')->delete($qrCodePath);
        }

        return true;
    }

    /**
     * Generate player activation URL
     */
    private function getPlayerActivationUrl(Player $player): string
    {
        return config('app.url') . "/api/player/activate?token={$player->activation_token}";
    }

    /**
     * Convert hex color to RGB array
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }

    /**
     * Check if QR code exists for player
     */
    public function playerHasQRCode(Player $player): bool
    {
        return Storage::disk('public')->exists($this->getPlayerQRCodePath($player));
    }

    /**
     * Get QR code file size in bytes
     */
    public function getQRCodeSize(Player $player): ?int
    {
        $qrCodePath = $this->getPlayerQRCodePath($player);

        if (Storage::disk('public')->exists($qrCodePath)) {
            return Storage::disk('public')->size($qrCodePath);
        }

        return null;
    }

    /**
     * Generate QR code with logo overlay
     */
    public function generateQRCodeWithLogo(
        string $data,
        string $logoPath,
        array $options = []
    ): string {
        // This would require additional image manipulation
        // For now, returning standard QR code
        // TODO: Implement logo overlay functionality using Intervention Image
        return $this->generateQRCode($data, $options);
    }

    /**
     * Clean up old QR codes for tenant
     */
    public function cleanupTenantQRCodes(Tenant $tenant, int $daysOld = 30): int
    {
        $deleted = 0;
        $tenantPath = "{$this->storagePath}/{$tenant->id}";

        if (!Storage::disk('public')->exists($tenantPath)) {
            return $deleted;
        }

        $files = Storage::disk('public')->allFiles($tenantPath);
        $cutoffTime = now()->subDays($daysOld);

        foreach ($files as $file) {
            $lastModified = Storage::disk('public')->lastModified($file);

            if ($lastModified < $cutoffTime->timestamp) {
                Storage::disk('public')->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}