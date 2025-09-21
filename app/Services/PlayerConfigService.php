<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerSettings;
use App\Services\TenantSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlayerConfigService
{
    private const CACHE_PREFIX = 'player_config:';
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private TenantSettingsService $tenantSettingsService
    ) {}

    /**
     * Get effective configuration for a player (tenant defaults + player overrides)
     */
    public function getEffectiveConfig(Player $player): array
    {
        $cacheKey = $this->getCacheKey($player->id);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($player) {
            return $this->buildEffectiveConfig($player);
        });
    }

    /**
     * Get a specific setting value for a player
     */
    public function getSetting(Player $player, string $key, $default = null)
    {
        $config = $this->getEffectiveConfig($player);
        return $config[$key] ?? $default;
    }

    /**
     * Set a setting value for a player
     */
    public function setSetting(Player $player, string $key, $value, string $type = 'string'): PlayerSettings
    {
        // Validate the setting
        $validation = PlayerSettings::validateSetting($key, $value);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException('Configuração inválida: ' . implode(', ', $validation['errors']));
        }

        $setting = PlayerSettings::setValue($player->id, $key, $validation['value'], $type, false);

        // Clear cache
        $this->clearCache($player);

        Log::info("Player setting updated", [
            'player_id' => $player->id,
            'player_name' => $player->name,
            'setting_key' => $key,
            'setting_value' => $value,
            'type' => $type,
        ]);

        return $setting;
    }

    /**
     * Reset a setting to tenant default
     */
    public function resetToDefault(Player $player, string $key): bool
    {
        $removed = PlayerSettings::removeSetting($player->id, $key);

        if ($removed) {
            $this->clearCache($player);

            Log::info("Player setting reset to default", [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'setting_key' => $key,
            ]);
        }

        return $removed;
    }

    /**
     * Reset all settings to tenant defaults
     */
    public function resetAllToDefaults(Player $player): int
    {
        $count = $player->playerSettings()->delete();

        if ($count > 0) {
            $this->clearCache($player);

            Log::info("All player settings reset to defaults", [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'settings_removed' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Get player settings with metadata (including inherited values)
     */
    public function getSettingsWithMetadata(Player $player): array
    {
        $availableSettings = PlayerSettings::getSettingsByCategory();
        $currentSettings = $this->getEffectiveConfig($player);
        $playerSettings = $player->playerSettings()->get()->keyBy('setting_key');

        $result = [];

        foreach ($availableSettings as $categoryKey => $category) {
            $result[$categoryKey] = [
                'name' => $category['name'],
                'settings' => [],
            ];

            foreach ($category['settings'] as $settingKey => $config) {
                $currentValue = $currentSettings[$settingKey] ?? $config['default'];
                $isCustom = $playerSettings->has($settingKey);
                $tenantDefault = $this->getTenantDefault($player, $settingKey, $config['default']);

                $result[$categoryKey]['settings'][$settingKey] = array_merge($config, [
                    'current_value' => $currentValue,
                    'is_custom' => $isCustom,
                    'tenant_default' => $tenantDefault,
                    'is_inherited' => !$isCustom,
                ]);
            }
        }

        return $result;
    }

    /**
     * Update multiple settings at once
     */
    public function updateSettings(Player $player, array $settings): array
    {
        $results = [];
        $errors = [];

        foreach ($settings as $key => $data) {
            try {
                $value = $data['value'] ?? $data;
                $type = $data['type'] ?? 'string';

                $results[$key] = $this->setSetting($player, $key, $value, $type);
            } catch (\Exception $e) {
                $errors[$key] = $e->getMessage();
                Log::error("Failed to update player setting", [
                    'player_id' => $player->id,
                    'setting_key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => $results,
            'errors' => $errors,
        ];
    }

    /**
     * Export player configuration
     */
    public function exportConfig(Player $player): array
    {
        return [
            'player_id' => $player->id,
            'player_name' => $player->name,
            'exported_at' => now()->toISOString(),
            'settings' => $this->getEffectiveConfig($player),
            'custom_settings' => $player->playerSettings()
                ->get()
                ->map(function ($setting) {
                    return [
                        'key' => $setting->setting_key,
                        'value' => $setting->setting_value,
                        'type' => $setting->setting_type,
                    ];
                })
                ->keyBy('key')
                ->toArray(),
        ];
    }

    /**
     * Import player configuration
     */
    public function importConfig(Player $player, array $settings): array
    {
        $results = [];

        foreach ($settings as $key => $data) {
            try {
                $value = $data['value'] ?? $data;
                $type = $data['type'] ?? 'string';

                $results[$key] = $this->setSetting($player, $key, $value, $type);
            } catch (\Exception $e) {
                $results[$key] = ['error' => $e->getMessage()];
            }
        }

        Log::info("Player configuration imported", [
            'player_id' => $player->id,
            'settings_count' => count($settings),
        ]);

        return $results;
    }

    /**
     * Get configuration for API sync
     */
    public function getConfigForSync(Player $player): array
    {
        $config = $this->getEffectiveConfig($player);

        // Add version hash for change detection
        $configHash = md5(json_encode($config));

        return [
            'config' => $config,
            'version' => $configHash,
            'last_updated' => $player->playerSettings()
                ->latest('updated_at')
                ->first()?->updated_at?->toISOString(),
        ];
    }

    /**
     * Build effective configuration by merging tenant defaults with player overrides
     */
    private function buildEffectiveConfig(Player $player): array
    {
        $availableSettings = PlayerSettings::getAvailableSettings();
        $config = [];

        // Start with default values
        foreach ($availableSettings as $key => $setting) {
            $config[$key] = $setting['default'];
        }

        // Apply tenant defaults for inheritable settings
        foreach ($availableSettings as $key => $setting) {
            if ($setting['inheritable']) {
                $tenantDefault = $this->getTenantDefault($player, $key, $setting['default']);
                $config[$key] = $tenantDefault;
            }
        }

        // Apply player-specific overrides
        $playerSettings = $player->playerSettings()->get();
        foreach ($playerSettings as $setting) {
            $config[$setting->setting_key] = $setting->setting_value;
        }

        return $config;
    }

    /**
     * Get tenant default for a setting
     */
    private function getTenantDefault(Player $player, string $key, $fallback)
    {
        // Map player setting keys to tenant setting keys
        $mapping = [
            'volume' => 'default_volume',
            'media_interval' => 'default_display_duration',
            'loop_enabled' => 'auto_restart',
        ];

        $tenantKey = $mapping[$key] ?? null;

        if ($tenantKey) {
            return $this->tenantSettingsService->get($player->tenant, $tenantKey, $fallback);
        }

        return $fallback;
    }

    /**
     * Cache helper methods
     */
    private function getCacheKey(int $playerId): string
    {
        return self::CACHE_PREFIX . $playerId;
    }

    private function clearCache(Player $player): void
    {
        Cache::forget($this->getCacheKey($player->id));

        Log::info("Player config cache cleared", [
            'player_id' => $player->id,
        ]);
    }

    /**
     * Clear cache for multiple players
     */
    public function clearCacheForPlayers(array $playerIds): void
    {
        foreach ($playerIds as $playerId) {
            Cache::forget($this->getCacheKey($playerId));
        }
    }

    /**
     * Get default configuration without player-specific overrides
     */
    public function getDefaultConfig(): array
    {
        $availableSettings = PlayerSettings::getAvailableSettings();
        $config = [];

        foreach ($availableSettings as $key => $setting) {
            $config[$key] = $setting['default'];
        }

        return $config;
    }

    /**
     * Validate all settings in a configuration array
     */
    public function validateConfig(array $config): array
    {
        $errors = [];
        $validatedConfig = [];

        foreach ($config as $key => $value) {
            $validation = PlayerSettings::validateSetting($key, $value);

            if ($validation['valid']) {
                $validatedConfig[$key] = $validation['value'];
            } else {
                $errors[$key] = $validation['errors'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'config' => $validatedConfig,
        ];
    }
}