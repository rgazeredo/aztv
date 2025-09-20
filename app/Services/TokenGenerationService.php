<?php

namespace App\Services;

use App\Models\PlayerActivationToken;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;
use Exception;

class TokenGenerationService
{
    public function generateActivationToken(Tenant $tenant, array $playerData = []): PlayerActivationToken
    {
        try {
            $token = PlayerActivationToken::generateForTenant($tenant, $playerData);

            // Generate QR code
            $qrCodePath = $this->generateQRCode($token);
            if ($qrCodePath) {
                $token->update(['qr_code_path' => $qrCodePath]);
            }

            Log::info('Activation token generated successfully', [
                'tenant_id' => $tenant->id,
                'token_id' => $token->id,
                'token' => $token->token,
                'activation_code' => $token->activation_code,
                'expires_at' => $token->expires_at,
            ]);

            return $token;

        } catch (Exception $e) {
            Log::error('Failed to generate activation token', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function generateQRCode(PlayerActivationToken $token): ?string
    {
        try {
            $activationUrl = $token->activation_url;
            $qrCodeData = $this->buildQRCodeData($token, $activationUrl);

            $qrCode = QrCode::format('png')
                ->size(300)
                ->margin(2)
                ->errorCorrection('M')
                ->generate($qrCodeData);

            $fileName = "qr_codes/activation_{$token->token}.png";
            $disk = config('filesystems.default', 'public');

            if (Storage::disk($disk)->put($fileName, $qrCode)) {
                Log::info('QR code generated successfully', [
                    'token_id' => $token->id,
                    'file_path' => $fileName,
                    'activation_url' => $activationUrl,
                ]);

                return $fileName;
            }

            throw new Exception('Failed to save QR code file');

        } catch (Exception $e) {
            Log::error('Failed to generate QR code', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function generateShortUrl(PlayerActivationToken $token): string
    {
        return $token->short_url ?? "/a/{$token->token}";
    }

    public function regenerateQRCode(PlayerActivationToken $token): bool
    {
        try {
            // Delete old QR code if exists
            if ($token->qr_code_path) {
                Storage::disk(config('filesystems.default', 'public'))
                    ->delete($token->qr_code_path);
            }

            $qrCodePath = $this->generateQRCode($token);

            if ($qrCodePath) {
                $token->update(['qr_code_path' => $qrCodePath]);
                return true;
            }

            return false;

        } catch (Exception $e) {
            Log::error('Failed to regenerate QR code', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getQRCodeUrl(PlayerActivationToken $token): ?string
    {
        if (!$token->qr_code_path) {
            return null;
        }

        $disk = config('filesystems.default', 'public');

        if ($disk === 'public') {
            return Storage::url($token->qr_code_path);
        }

        // For other storage drivers, generate temporary URL
        return Storage::disk($disk)->temporaryUrl(
            $token->qr_code_path,
            now()->addHours(1)
        );
    }

    public function revokeToken(PlayerActivationToken $token): bool
    {
        try {
            // Delete QR code file if exists
            if ($token->qr_code_path) {
                Storage::disk(config('filesystems.default', 'public'))
                    ->delete($token->qr_code_path);
            }

            $token->delete();

            Log::info('Activation token revoked', [
                'token_id' => $token->id,
                'token' => $token->token,
                'tenant_id' => $token->tenant_id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to revoke token', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function cleanupExpiredTokens(): int
    {
        try {
            $expiredTokens = PlayerActivationToken::expired()->get();
            $deletedCount = 0;

            foreach ($expiredTokens as $token) {
                if ($this->revokeToken($token)) {
                    $deletedCount++;
                }
            }

            Log::info('Expired tokens cleaned up', [
                'deleted_count' => $deletedCount,
                'total_found' => $expiredTokens->count(),
            ]);

            return $deletedCount;

        } catch (Exception $e) {
            Log::error('Failed to cleanup expired tokens', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function getTokenStatistics(Tenant $tenant = null): array
    {
        $query = PlayerActivationToken::query();

        if ($tenant) {
            $query->forTenant($tenant->id);
        }

        $total = $query->count();
        $active = $query->active()->count();
        $used = $query->used()->count();
        $expired = $query->expired()->count();

        return [
            'total' => $total,
            'active' => $active,
            'used' => $used,
            'expired' => $expired,
            'usage_rate' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
        ];
    }

    private function buildQRCodeData(PlayerActivationToken $token, string $activationUrl): string
    {
        // Create structured data for better Android app integration
        return json_encode([
            'type' => 'player_activation',
            'url' => $activationUrl,
            'token' => $token->token,
            'code' => $token->activation_code,
            'tenant' => $token->tenant->slug,
            'expires_at' => $token->expires_at->toISOString(),
        ]);
    }

    public function validateActivationData(string $identifier): array
    {
        $token = PlayerActivationToken::findByTokenOrCode($identifier);

        if (!$token) {
            return [
                'valid' => false,
                'error' => 'Token não encontrado',
                'token' => null,
            ];
        }

        if (!$token->isValid()) {
            $error = $token->is_used ? 'Token já foi utilizado' : 'Token expirado';
            return [
                'valid' => false,
                'error' => $error,
                'token' => $token,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'token' => $token,
        ];
    }
}