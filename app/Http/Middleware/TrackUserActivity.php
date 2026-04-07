<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Analytics\UserActivityLog;
use App\Models\Analytics\UserFeatureUsage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $user = auth()->user();

            if ($user === null) {
                return;
            }

            $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $routeName = $request->route()?->getName();
            $prefix = $request->route()?->getPrefix() ?? '';

            $module = $this->resolveModule($prefix);
            $action = $this->resolveAction($routeName);
            $entityType = $this->resolveEntityType($routeName);

            $userAgent = $request->userAgent() ?? '';
            $deviceType = $this->resolveDeviceType($userAgent);
            $browser = $this->resolveBrowser($userAgent);
            $os = $this->resolveOs($userAgent);

            $requestSummary = $this->sanitizeRequestParams($request->all());

            $log = UserActivityLog::create([
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'method' => $request->method(),
                'route_name' => $routeName,
                'module' => $module,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => null,
                'response_status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'ip_address' => $request->ip(),
                'user_agent' => $userAgent,
                'device_type' => $deviceType,
                'browser' => $browser,
                'os' => $os,
                'request_summary' => $requestSummary ?: null,
            ]);

            $this->upsertFeatureUsage($user->id, $user->organization_id, $module, $action, $request->method(), $durationMs);
        } catch (\Throwable $e) {
            Log::warning('TrackUserActivity middleware failed', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }
    }

    private function resolveModule(string $prefix): string
    {
        return match (true) {
            str_contains($prefix, '/sales') => 'sales',
            str_contains($prefix, '/hr') => 'hr',
            str_contains($prefix, '/inventory') => 'inventory',
            str_contains($prefix, '/purchase') => 'purchase',
            str_contains($prefix, '/accounting') => 'accounting',
            str_contains($prefix, '/manufacturing') => 'manufacturing',
            str_contains($prefix, '/crm') => 'crm',
            str_contains($prefix, '/reports') => 'reports',
            default => 'core',
        };
    }

    private function resolveAction(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        $parts = explode('.', $routeName);

        return end($parts) ?: null;
    }

    private function resolveEntityType(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        $parts = explode('.', $routeName);

        if (count($parts) >= 2) {
            return $parts[count($parts) - 2];
        }

        return null;
    }

    private function resolveDeviceType(string $userAgent): string
    {
        if (
            str_contains($userAgent, 'Mobile') ||
            str_contains($userAgent, 'Android') ||
            str_contains($userAgent, 'iPhone')
        ) {
            return UserActivityLog::DEVICE_MOBILE;
        }

        if (
            str_contains($userAgent, 'iPad') ||
            str_contains($userAgent, 'Tablet')
        ) {
            return UserActivityLog::DEVICE_TABLET;
        }

        return UserActivityLog::DEVICE_DESKTOP;
    }

    private function resolveBrowser(string $userAgent): string
    {
        if (str_contains($userAgent, 'Edg/') || str_contains($userAgent, 'Edge/')) {
            return 'Edge';
        }

        if (str_contains($userAgent, 'Chrome/')) {
            return 'Chrome';
        }

        if (str_contains($userAgent, 'Firefox/')) {
            return 'Firefox';
        }

        if (str_contains($userAgent, 'Safari/')) {
            return 'Safari';
        }

        return 'Other';
    }

    private function resolveOs(string $userAgent): string
    {
        if (str_contains($userAgent, 'Windows')) {
            return 'Windows';
        }

        if (str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS')) {
            return 'Mac';
        }

        if (str_contains($userAgent, 'Android')) {
            return 'Android';
        }

        if (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') || str_contains($userAgent, 'iOS')) {
            return 'iOS';
        }

        if (str_contains($userAgent, 'Linux')) {
            return 'Linux';
        }

        return 'Other';
    }

    private function sanitizeRequestParams(array $params): array
    {
        $sensitivePattern = '/password|token|secret|key|auth|credit|card/i';

        $sanitized = [];
        foreach ($params as $key => $value) {
            if (preg_match($sensitivePattern, (string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->sanitizeRequestParams($value);
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function upsertFeatureUsage(
        int $userId,
        int $organizationId,
        string $module,
        ?string $feature,
        string $method,
        int $durationMs
    ): void {
        if ($feature === null) {
            return;
        }

        $record = UserFeatureUsage::firstOrCreate(
            [
                'user_id' => $userId,
                'module' => $module,
                'feature' => $feature,
                'usage_date' => today(),
            ],
            [
                'organization_id' => $organizationId,
                'access_count' => 0,
                'create_count' => 0,
                'update_count' => 0,
                'delete_count' => 0,
                'total_duration_ms' => 0,
            ]
        );

        $increments = ['access_count' => 1, 'total_duration_ms' => $durationMs];

        $increments['create_count'] = match ($method) {
            'POST' => 1,
            default => 0,
        };
        $increments['update_count'] = match ($method) {
            'PUT', 'PATCH' => 1,
            default => 0,
        };
        $increments['delete_count'] = match ($method) {
            'DELETE' => 1,
            default => 0,
        };

        $record->increment('access_count', $increments['access_count']);
        $record->increment('total_duration_ms', $increments['total_duration_ms']);

        if ($increments['create_count'] > 0) {
            $record->increment('create_count');
        }

        if ($increments['update_count'] > 0) {
            $record->increment('update_count');
        }

        if ($increments['delete_count'] > 0) {
            $record->increment('delete_count');
        }
    }
}
