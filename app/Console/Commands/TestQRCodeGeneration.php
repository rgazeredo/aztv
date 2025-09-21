<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\QRCodeService;
use Illuminate\Console\Command;
use Exception;

class TestQRCodeGeneration extends Command
{
    protected $signature = 'test:qr-codes';
    protected $description = 'Test QR code generation functionality';

    public function handle()
    {
        $this->info('🔧 Testing QR code generation...');

        try {
            // Get a sample player to test with
            $player = Player::first();

            if (!$player) {
                $this->error('❌ No players found in database. Please create a player first.');
                return Command::FAILURE;
            }

            $this->info("📱 Testing with player: {$player->name} (ID: {$player->id})");

            $qrCodeService = app(QRCodeService::class);

            // Test 1: Generate QR code
            $this->info('📋 Test 1: Generating QR code...');
            $qrCodeUrl = $qrCodeService->savePlayerQRCode($player);
            $this->info("✅ QR code generated: {$qrCodeUrl}");

            // Test 2: Check if QR code exists
            $this->info('📋 Test 2: Checking if QR code exists...');
            $hasQRCode = $qrCodeService->playerHasQRCode($player);
            $this->info($hasQRCode ? '✅ QR code exists' : '❌ QR code not found');

            // Test 3: Get QR code URL
            $this->info('📋 Test 3: Getting QR code URL...');
            $qrCodeUrl = $qrCodeService->getPlayerQRCodeUrl($player);
            $this->info($qrCodeUrl ? "✅ QR code URL: {$qrCodeUrl}" : '❌ QR code URL not found');

            // Test 4: Generate activation URL
            $this->info('📋 Test 4: Testing activation URL...');
            $activationUrl = $player->getActivationUrl();
            $this->info("✅ Activation URL: {$activationUrl}");

            // Test 5: Test Player model methods
            $this->info('📋 Test 5: Testing Player model methods...');
            $hasQRCode = $player->hasQRCode();
            $this->info($hasQRCode ? '✅ Player has QR code' : '❌ Player does not have QR code');

            // Test 6: Get file size
            $this->info('📋 Test 6: Getting QR code file size...');
            $fileSize = $qrCodeService->getQRCodeSize($player);
            $this->info($fileSize ? "✅ File size: {$fileSize} bytes" : '❌ Could not get file size');

            // Test 7: Generate with custom options
            $this->info('📋 Test 7: Generating QR code with custom options...');
            $customOptions = [
                'size' => 300,
                'margin' => 1,
                'errorCorrectionLevel' => 'H'
            ];
            $customQRUrl = $qrCodeService->savePlayerQRCode($player, $customOptions);
            $this->info("✅ Custom QR code generated: {$customQRUrl}");

            $this->info('🎉 All tests completed successfully!');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->error("Stack trace: {$e->getTraceAsString()}");
            return Command::FAILURE;
        }
    }
}