<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\System\Setting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Manages typed, validated, Redis-cached settings for each tenant organization.
 *
 * Responsibilities:
 * - Read and write organization settings stored as group+key rows in the settings table
 * - Validate values against a static definition registry (type, min/max, allowed options)
 * - Cache individual setting reads in Laravel's default cache store (TTL: 1 hour)
 * - Cache the full settings snapshot via CacheService's semi-dynamic tier (also 1 hour)
 * - Provide defaults from definitions when no DB row exists for a key
 * - Apply regional defaults (currency, tax rate, fiscal year) for a given country code
 *   without overwriting existing values (unless forced)
 * - Parse dot-notation keys ("accounting.default_currency") into [group, key] pairs
 *
 * Side Effects:
 * - Writes to the settings table on set() / setMany() / delete() / initializeByCountry()
 * - Calls Cache::forget() for the per-key cache entry on every write
 * - Calls CacheService.forgetSemi() for the all-settings snapshot on every write
 *
 * Idempotency:
 * - set() uses updateOrCreate(); calling it twice with the same value is safe
 * - initializeByCountry() is idempotent by default (skips keys that already exist);
 *   pass $force=true to overwrite existing values
 * - delete() is idempotent; deleting a non-existent key silently succeeds
 *
 * CONTRACT:
 * - Only keys declared in the $definitions registry are accepted; unknown keys cause
 *   an InvalidArgumentException in validateSetting()
 * - Callers must pass a valid organization_id; no tenancy is enforced beyond the
 *   WHERE clause — there is no global scope on the settings table
 * - Boolean values must be PHP bool or one of [0, 1, '0', '1', 'true', 'false']
 */
class SettingsService
{
    private const CACHE_PREFIX = 'settings:';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly CacheService $cache,
    ) {}

    /**
     * Setting definitions with types and defaults.
     */
    private array $definitions = [
        // Organization Settings
        'org.name' => ['type' => 'string', 'required' => true],
        'org.logo_url' => ['type' => 'string', 'nullable' => true],
        'org.primary_color' => ['type' => 'string', 'default' => '#3B82F6'],
        'org.date_format' => ['type' => 'string', 'default' => 'Y-m-d', 'options' => ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y']],
        'org.time_format' => ['type' => 'string', 'default' => 'H:i', 'options' => ['H:i', 'h:i A']],
        'org.first_day_of_week' => ['type' => 'integer', 'default' => 0, 'min' => 0, 'max' => 6],

        // Accounting Settings
        'accounting.fiscal_year_start_month' => ['type' => 'integer', 'default' => 1, 'min' => 1, 'max' => 12],
        'accounting.default_currency' => ['type' => 'string', 'default' => 'SAR'],
        'accounting.multi_currency_enabled' => ['type' => 'boolean', 'default' => true],
        'accounting.auto_post_journals' => ['type' => 'boolean', 'default' => false],
        'accounting.require_journal_approval' => ['type' => 'boolean', 'default' => false],

        // Invoice Settings
        'invoice.prefix' => ['type' => 'string', 'default' => 'INV-'],
        'invoice.starting_number' => ['type' => 'integer', 'default' => 1, 'min' => 1],
        'invoice.due_days' => ['type' => 'integer', 'default' => 30, 'min' => 0],
        'invoice.auto_send_email' => ['type' => 'boolean', 'default' => false],
        'invoice.show_tax_breakdown' => ['type' => 'boolean', 'default' => true],

        // Tax Settings
        'tax.default_rate' => ['type' => 'decimal', 'default' => 15.00, 'min' => 0, 'max' => 100],
        'tax.inclusive_pricing' => ['type' => 'boolean', 'default' => false],

        // Notification Settings
        'notifications.email_enabled' => ['type' => 'boolean', 'default' => true],
        'notifications.low_stock_threshold' => ['type' => 'integer', 'default' => 10, 'min' => 0],

        // Security Settings
        'security.password_expiry_days' => ['type' => 'integer', 'default' => 0, 'min' => 0], // 0 = never
        'security.session_timeout_minutes' => ['type' => 'integer', 'default' => 60, 'min' => 5],
        'security.require_2fa' => ['type' => 'boolean', 'default' => false],
    ];

    /**
     * Get a setting value.
     */
    public function get(string $key, int $organizationId, mixed $default = null): mixed
    {
        $cacheKey = $this->getCacheKey($key, $organizationId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $organizationId, $default) {
            [$group, $settingKey] = $this->parseKey($key);

            $setting = Setting::where('organization_id', $organizationId)
                ->where('group', $group)
                ->where('key', $settingKey)
                ->first();

            if (!$setting) {
                return $default ?? $this->getDefault($key);
            }

            return $this->castValue($setting->value, $this->getType($key));
        });
    }

    /**
     * Get multiple settings at once.
     */
    public function getMany(array $keys, int $organizationId): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $organizationId);
        }

        return $result;
    }

    /**
     * Get all settings for an organization.
     * Results are cached in the semi-dynamic tier (1 h) via CacheService.
     */
    public function getAll(int $organizationId): array
    {
        return $this->cache->organizationSettings($organizationId, function () use ($organizationId): array {
            // Build a lookup map keyed by "group.key" dot-notation from DB rows
            $dbRows = Setting::where('organization_id', $organizationId)
                ->get(['group', 'key', 'value']);

            $settings = [];
            foreach ($dbRows as $row) {
                $dotKey = $row->group . '.' . $row->key;
                $settings[$dotKey] = $row->value;
            }

            // Merge with defaults
            $result = [];
            foreach ($this->definitions as $key => $definition) {
                if (isset($settings[$key])) {
                    $result[$key] = $this->castValue($settings[$key], $definition['type']);
                } else {
                    $result[$key] = $definition['default'] ?? null;
                }
            }

            return $result;
        });
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value, int $organizationId): void
    {
        $this->validateSetting($key, $value);

        [$group, $settingKey] = $this->parseKey($key);

        Setting::updateOrCreate(
            ['organization_id' => $organizationId, 'group' => $group, 'key' => $settingKey],
            ['value' => $this->serializeValue($value)]
        );

        $this->clearCache($key, $organizationId);
        // Also bust the aggregated all-settings semi-dynamic cache entry.
        $this->cache->forgetSemi($organizationId, 'settings');
    }

    /**
     * Set multiple settings at once.
     */
    public function setMany(array $settings, int $organizationId): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value, $organizationId);
        }
    }

    /**
     * Delete a setting (revert to default).
     */
    public function delete(string $key, int $organizationId): void
    {
        [$group, $settingKey] = $this->parseKey($key);

        Setting::where('organization_id', $organizationId)
            ->where('group', $group)
            ->where('key', $settingKey)
            ->delete();

        $this->clearCache($key, $organizationId);
        // Also bust the aggregated all-settings semi-dynamic cache entry.
        $this->cache->forgetSemi($organizationId, 'settings');
    }

    /**
     * Get setting definition.
     */
    public function getDefinition(string $key): ?array
    {
        return $this->definitions[$key] ?? null;
    }

    /**
     * Get all setting definitions.
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * Validate a setting value against its definition.
     */
    protected function validateSetting(string $key, mixed $value): void
    {
        $definition = $this->definitions[$key] ?? null;

        if (!$definition) {
            throw new InvalidArgumentException("Unknown setting: {$key}");
        }

        // Check required
        if (($definition['required'] ?? false) && $value === null) {
            throw new InvalidArgumentException("Setting '{$key}' is required");
        }

        // Check nullable
        if ($value === null && ($definition['nullable'] ?? false)) {
            return;
        }

        // Type validation
        switch ($definition['type']) {
            case 'integer':
                if (!is_int($value) && !is_numeric($value)) {
                    throw new InvalidArgumentException("Setting '{$key}' must be an integer");
                }
                if (isset($definition['min']) && $value < $definition['min']) {
                    throw new InvalidArgumentException("Setting '{$key}' must be at least {$definition['min']}");
                }
                if (isset($definition['max']) && $value > $definition['max']) {
                    throw new InvalidArgumentException("Setting '{$key}' must be at most {$definition['max']}");
                }
                break;

            case 'decimal':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Setting '{$key}' must be a number");
                }
                if (isset($definition['min']) && $value < $definition['min']) {
                    throw new InvalidArgumentException("Setting '{$key}' must be at least {$definition['min']}");
                }
                if (isset($definition['max']) && $value > $definition['max']) {
                    throw new InvalidArgumentException("Setting '{$key}' must be at most {$definition['max']}");
                }
                break;

            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    throw new InvalidArgumentException("Setting '{$key}' must be a boolean");
                }
                break;

            case 'string':
                if (!is_string($value)) {
                    throw new InvalidArgumentException("Setting '{$key}' must be a string");
                }
                if (isset($definition['options']) && !in_array($value, $definition['options'], true)) {
                    $options = implode(', ', $definition['options']);
                    throw new InvalidArgumentException("Setting '{$key}' must be one of: {$options}");
                }
                break;
        }
    }

    /**
     * Cast a value to the appropriate type.
     */
    protected function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Serialize a value for storage.
     */
    protected function serializeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Get the type for a setting.
     */
    protected function getType(string $key): string
    {
        return $this->definitions[$key]['type'] ?? 'string';
    }

    /**
     * Get the default value for a setting.
     */
    protected function getDefault(string $key): mixed
    {
        return $this->definitions[$key]['default'] ?? null;
    }

    /**
     * Get cache key — includes group so it matches the DB storage layout.
     */
    protected function getCacheKey(string $key, int $organizationId): string
    {
        [$group, $settingKey] = $this->parseKey($key);
        return self::CACHE_PREFIX . "{$organizationId}:{$group}.{$settingKey}";
    }

    /**
     * Clear cache for a setting.
     */
    protected function clearCache(string $key, int $organizationId): void
    {
        Cache::forget($this->getCacheKey($key, $organizationId));
    }

    /**
     * Clear all settings cache for an organization.
     */
    public function clearAllCache(int $organizationId): void
    {
        foreach (array_keys($this->definitions) as $key) {
            $this->clearCache($key, $organizationId);
        }
    }

    /**
     * Apply all regional defaults for an organization based on its country code.
     * Existing settings are NOT overwritten unless $force = true.
     *
     * @return array{applied: list<string>, skipped: list<string>, country_code: string}
     */
    public function initializeByCountry(int $organizationId, string $countryCode, bool $force = false): array
    {
        $defaults = app(\App\Services\Core\RegionalDefaultsService::class)->getDefaultsForCountry($countryCode);
        $applied  = [];
        $skipped  = [];

        foreach ($defaults as $key => $value) {
            $existing = $this->getRaw($organizationId, $key);

            if ($existing !== null && !$force) {
                $skipped[] = $key;
                continue;
            }

            try {
                $this->set($key, $value, $organizationId);
                $applied[] = $key;
            } catch (\Throwable) {
                // Skip keys not present in definitions gracefully
                $skipped[] = $key;
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped, 'country_code' => $countryCode];
    }

    /**
     * Returns the raw DB value (or null) without falling back to defaults.
     */
    protected function getRaw(int $organizationId, string $key): mixed
    {
        [$group, $settingKey] = $this->parseKey($key);

        return Setting::where('organization_id', $organizationId)
            ->where('group', $group)
            ->where('key', $settingKey)
            ->value('value');
    }

    /**
     * Parse a dot-notation key like "accounting.default_currency" into [group, key].
     * If there is no dot, the group defaults to "general".
     */
    protected function parseKey(string $dotKey): array
    {
        $parts = explode('.', $dotKey, 2);

        return count($parts) === 2 ? $parts : ['general', $dotKey];
    }
}
