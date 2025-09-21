<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TenantSettingsService
{
    private const CACHE_PREFIX = 'tenant_settings:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a setting value for a tenant
     */
    public function get(Tenant $tenant, string $key, $default = null)
    {
        $cacheKey = $this->getCacheKey($tenant->id, $key);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant, $key, $default) {
            return TenantSettings::getValue($tenant->id, $key, $default);
        });
    }

    /**
     * Set a setting value for a tenant
     */
    public function set(Tenant $tenant, string $category, string $key, $value, string $type = 'string', bool $isEncrypted = false): TenantSettings
    {
        $setting = TenantSettings::setValue($tenant->id, $category, $key, $value, $type, $isEncrypted);

        // Invalidate cache for this specific setting
        $this->invalidateCache($tenant->id, $key);

        // Also invalidate category cache
        $this->invalidateCategoryCache($tenant->id, $category);

        Log::info("Tenant setting updated", [
            'tenant_id' => $tenant->id,
            'category' => $category,
            'key' => $key,
            'type' => $type,
            'encrypted' => $isEncrypted,
        ]);

        return $setting;
    }

    /**
     * Get all settings for a category
     */
    public function getByCategory(Tenant $tenant, string $category): array
    {
        $cacheKey = $this->getCategoryCacheKey($tenant->id, $category);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant, $category) {
            return TenantSettings::getByCategory($tenant->id, $category);
        });
    }

    /**
     * Update multiple settings at once
     */
    public function updateCategory(Tenant $tenant, string $category, array $settings): array
    {
        $results = [];

        foreach ($settings as $key => $data) {
            $value = $data['value'] ?? $data;
            $type = $data['type'] ?? 'string';
            $isEncrypted = $data['encrypted'] ?? false;

            try {
                $results[$key] = $this->set($tenant, $category, $key, $value, $type, $isEncrypted);
            } catch (\Exception $e) {
                Log::error("Failed to update tenant setting", [
                    'tenant_id' => $tenant->id,
                    'category' => $category,
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);

                $results[$key] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Reset settings to defaults for a category
     */
    public function resetCategory(Tenant $tenant, string $category): void
    {
        $defaults = TenantSettings::getDefaultCategories();

        if (!isset($defaults[$category])) {
            throw new \InvalidArgumentException("Invalid category: {$category}");
        }

        $categorySettings = $defaults[$category]['settings'];

        // Delete existing settings for this category
        TenantSettings::forTenant($tenant->id)->byCategory($category)->delete();

        // Set default values
        foreach ($categorySettings as $key => $config) {
            $this->set(
                $tenant,
                $category,
                $key,
                $config['default'],
                $config['type']
            );
        }

        Log::info("Tenant settings reset to defaults", [
            'tenant_id' => $tenant->id,
            'category' => $category,
        ]);
    }

    /**
     * Get all settings for a tenant, organized by category
     */
    public function getAllSettings(Tenant $tenant): array
    {
        $cacheKey = $this->getAllCacheKey($tenant->id);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant) {
            $settings = TenantSettings::forTenant($tenant->id)->get();
            $organized = [];

            foreach ($settings as $setting) {
                $organized[$setting->category][$setting->key] = $setting->getValue();
            }

            return $organized;
        });
    }

    /**
     * Initialize default settings for a new tenant
     */
    public function initializeDefaults(Tenant $tenant): void
    {
        $categories = TenantSettings::getDefaultCategories();

        foreach ($categories as $categoryKey => $category) {
            foreach ($category['settings'] as $settingKey => $config) {
                $this->set(
                    $tenant,
                    $categoryKey,
                    $settingKey,
                    $config['default'],
                    $config['type']
                );
            }
        }

        Log::info("Default settings initialized for tenant", [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Get settings with their configuration metadata
     */
    public function getSettingsWithMetadata(Tenant $tenant, string $category): array
    {
        $defaults = TenantSettings::getDefaultCategories();
        $categoryConfig = $defaults[$category] ?? null;

        if (!$categoryConfig) {
            throw new \InvalidArgumentException("Invalid category: {$category}");
        }

        $currentSettings = $this->getByCategory($tenant, $category);
        $result = [];

        foreach ($categoryConfig['settings'] as $key => $config) {
            $result[$key] = array_merge($config, [
                'current_value' => $currentSettings[$key] ?? $config['default'],
                'is_default' => !isset($currentSettings[$key]) || $currentSettings[$key] === $config['default'],
            ]);
        }

        return [
            'category' => $categoryConfig,
            'settings' => $result,
        ];
    }

    /**
     * Export settings for a tenant (useful for backups or migrations)
     */
    public function exportSettings(Tenant $tenant): array
    {
        return [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'exported_at' => now()->toISOString(),
            'settings' => $this->getAllSettings($tenant),
        ];
    }

    /**
     * Import settings for a tenant
     */
    public function importSettings(Tenant $tenant, array $settings): void
    {
        foreach ($settings as $category => $categorySettings) {
            $this->updateCategory($tenant, $category, array_map(function ($value) {
                return ['value' => $value];
            }, $categorySettings));
        }

        Log::info("Settings imported for tenant", [
            'tenant_id' => $tenant->id,
            'categories_count' => count($settings),
        ]);
    }

    /**
     * Cache helper methods
     */
    private function getCacheKey(int $tenantId, string $key): string
    {
        return self::CACHE_PREFIX . "{$tenantId}:setting:{$key}";
    }

    private function getCategoryCacheKey(int $tenantId, string $category): string
    {
        return self::CACHE_PREFIX . "{$tenantId}:category:{$category}";
    }

    private function getAllCacheKey(int $tenantId): string
    {
        return self::CACHE_PREFIX . "{$tenantId}:all";
    }

    private function invalidateCache(int $tenantId, string $key): void
    {
        Cache::forget($this->getCacheKey($tenantId, $key));
        Cache::forget($this->getAllCacheKey($tenantId));
    }

    private function invalidateCategoryCache(int $tenantId, string $category): void
    {
        Cache::forget($this->getCategoryCacheKey($tenantId, $category));
        Cache::forget($this->getAllCacheKey($tenantId));
    }

    /**
     * Clear all cache for a tenant
     */
    public function clearCache(Tenant $tenant): void
    {
        $pattern = self::CACHE_PREFIX . $tenant->id . ':*';

        // Note: This is a simplified approach. In production, you might want to
        // use Redis SCAN command or keep track of cache keys more systematically
        Cache::forget($this->getAllCacheKey($tenant->id));

        Log::info("Cache cleared for tenant settings", [
            'tenant_id' => $tenant->id,
        ]);
    }
}