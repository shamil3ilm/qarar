<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\TenantRateLimitConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TenantRateLimitService
{
    public function getConfig(int $orgId): TenantRateLimitConfig
    {
        return TenantRateLimitConfig::firstOrCreate(
            ['organization_id' => $orgId],
            [
                'uuid'                => (string) Str::uuid(),
                'requests_per_minute' => 60,
                'requests_per_hour'   => 1000,
                'requests_per_day'    => 10000,
                'burst_limit'         => 100,
                'is_unlimited'        => false,
            ]
        );
    }

    public function checkRateLimit(int $orgId, string $endpoint): bool
    {
        $config = $this->getConfig($orgId);

        if ($config->is_unlimited) {
            return true;
        }

        $key   = "rate_limit:{$orgId}:" . now()->format('H:i');
        $count = Cache::increment($key);
        if ($count === 1) {
            Cache::expire($key, 60);
        }

        return $count <= $config->requests_per_minute;
    }

    public function updateConfig(int $orgId, array $config): TenantRateLimitConfig
    {
        $existing = $this->getConfig($orgId);
        $existing->update($config);
        return $existing->fresh();
    }

    public function getRateLimitStats(int $orgId): array
    {
        $config = $this->getConfig($orgId);
        $minuteKey = "rate_limit:{$orgId}:" . now()->format('H:i');

        return [
            'config'             => $config,
            'current_minute_hits' => (int) Cache::get($minuteKey, 0),
        ];
    }
}
