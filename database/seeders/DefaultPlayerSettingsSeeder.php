<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Services\PlayerConfigService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DefaultPlayerSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configService = app(PlayerConfigService::class);
        $players = Player::all();

        $this->command->info("Initializing settings for {$players->count()} players...");

        foreach ($players as $player) {
            try {
                // Set some example custom configurations
                $configService->setSetting($player, 'volume', rand(70, 90), 'integer');
                $configService->setSetting($player, 'media_interval', rand(5, 15), 'integer');
                $configService->setSetting($player, 'loop_enabled', (bool) rand(0, 1), 'boolean');

                // Set visual theme for some players
                if (rand(0, 1)) {
                    $themes = [
                        [
                            'primary_color' => '#3b82f6',
                            'secondary_color' => '#64748b',
                            'background_color' => '#ffffff',
                            'text_color' => '#1f2937',
                            'font_family' => 'Inter',
                            'logo_url' => null,
                        ],
                        [
                            'primary_color' => '#ef4444',
                            'secondary_color' => '#f97316',
                            'background_color' => '#1f2937',
                            'text_color' => '#f9fafb',
                            'font_family' => 'Roboto',
                            'logo_url' => null,
                        ],
                        [
                            'primary_color' => '#10b981',
                            'secondary_color' => '#06b6d4',
                            'background_color' => '#f8fafc',
                            'text_color' => '#0f172a',
                            'font_family' => 'Poppins',
                            'logo_url' => null,
                        ]
                    ];

                    $theme = $themes[array_rand($themes)];
                    $configService->setSetting($player, 'visual_theme', $theme, 'json');
                }

                // Set access password for some players
                if (rand(0, 2) === 0) {
                    $passwords = ['admin123', 'player456', 'secure789', 'config000'];
                    $password = $passwords[array_rand($passwords)];
                    $configService->setSetting($player, 'access_password', $password, 'string');
                }

                $this->command->info("✓ Settings initialized for player: {$player->name} (ID: {$player->id})");
            } catch (\Exception $e) {
                $this->command->error("✗ Failed to initialize settings for player {$player->id}: {$e->getMessage()}");
                Log::error("Failed to initialize player settings", [
                    'player_id' => $player->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->command->info("Default player settings seeding completed!");
    }
}