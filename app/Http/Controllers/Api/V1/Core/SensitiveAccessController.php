<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\Sales\Contact;
use App\Services\Core\SensitiveAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class SensitiveAccessController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;
    private const TOKEN_TTL_SECONDS = 900;

    /**
     * Verify the user's password and issue a short-lived access token for a specific resource.
     */
    public function requestAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'resource_type' => ['required', 'string', 'in:contact,employee'],
            'resource_id' => ['required', 'string'],
        ]);

        $user = auth('api')->user();
        $rateLimitKey = "sensitive_access_attempts:{$user->id}";

        if (Cache::get($rateLimitKey, 0) >= self::MAX_ATTEMPTS) {
            return $this->error(
                'Too many failed attempts. Try again in ' . self::LOCKOUT_MINUTES . ' minutes.',
                'OPERATION_FAILED',
                429
            );
        }

        if (! Hash::check($validated['password'], $user->password)) {
            Cache::add($rateLimitKey, 0, now()->addMinutes(self::LOCKOUT_MINUTES));
            Cache::increment($rateLimitKey);

            return $this->error('Invalid password.', 'OPERATION_FAILED', 401);
        }

        Cache::forget($rateLimitKey);

        $payload = json_encode([
            'user_id' => $user->id,
            'resource_type' => $validated['resource_type'],
            'resource_id' => $validated['resource_id'],
            'expires' => now()->addSeconds(self::TOKEN_TTL_SECONDS)->timestamp,
        ]);

        $token = Crypt::encrypt($payload);

        $this->logAudit(
            $user->id,
            'sensitive_data_access_granted',
            $validated['resource_type'],
            $validated['resource_id'],
            $request->ip()
        );

        return $this->success([
            'access_token' => $token,
            'expires_in' => self::TOKEN_TTL_SECONDS,
        ], 'Access token issued.');
    }

    /**
     * Return unmasked sensitive fields for a resource after validating the access token.
     */
    public function reveal(Request $request, string $resourceType, string $resourceId): JsonResponse
    {
        $token = $request->header('X-Sensitive-Token') ?? $request->input('access_token');

        if (! $token) {
            return $this->error('Access token is required.', 'OPERATION_FAILED', 422);
        }

        $payload = $this->validateToken($token, $resourceType, $resourceId);

        if ($payload === null) {
            return $this->error('Invalid or expired access token.', 'OPERATION_FAILED', 401);
        }

        $user = auth('api')->user();
        $fields = $this->loadSensitiveFields($resourceType, $resourceId);

        if ($fields === null) {
            return $this->error('Resource not found.', 'OPERATION_FAILED', 404);
        }

        $this->logAudit(
            $user->id,
            'sensitive_data_revealed',
            $resourceType,
            $resourceId,
            $request->ip()
        );

        // Also write to the unified sensitive_access_logs table so the report controller sees it.
        app(SensitiveAccessService::class)->logAccess(
            modelType: $resourceType,
            modelId:   (int) $resourceId,
            fields:    implode(',', array_keys($fields)),
            action:    'read',
        );

        return $this->success(['fields' => $fields], 'Sensitive data retrieved.');
    }

    /**
     * Decrypt and validate the access token payload.
     * Returns the decoded payload array on success, null on any failure.
     * Tokens are single-use — they are consumed immediately on first successful validation.
     */
    private function validateToken(string $token, string $resourceType, string $resourceId): ?array
    {
        try {
            $decoded = json_decode(Crypt::decrypt($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $user = auth('api')->user();

        if (
            ! isset($decoded['user_id'], $decoded['resource_type'], $decoded['resource_id'], $decoded['expires'])
            || $decoded['user_id'] !== $user->id
            || $decoded['resource_type'] !== $resourceType
            || $decoded['resource_id'] !== $resourceId
            || $decoded['expires'] < now()->timestamp
        ) {
            return null;
        }

        // Enforce single-use: reject if this token has already been consumed.
        $usedKey = 'sensitive_token_used:' . hash('sha256', $token);
        if (Cache::has($usedKey)) {
            return null;
        }
        Cache::put($usedKey, true, self::TOKEN_TTL_SECONDS);

        return $decoded;
    }

    /**
     * Load the plaintext sensitive fields for the requested resource.
     * Returns a key–value map, or null when the resource does not exist.
     */
    private function loadSensitiveFields(string $resourceType, string $resourceId): ?array
    {
        return match ($resourceType) {
            'contact' => $this->contactFields($resourceId),
            'employee' => $this->employeeFields($resourceId),
            default => null,
        };
    }

    private function contactFields(string $resourceId): ?array
    {
        $organizationId = auth('api')->user()->organization_id;

        $contact = Contact::withoutGlobalScopes()
            ->where('uuid', $resourceId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($contact === null) {
            return null;
        }

        return [
            'tax_number' => $contact->tax_number,
        ];
    }

    private function employeeFields(string $resourceId): ?array
    {
        $organizationId = auth('api')->user()->organization_id;

        $employee = Employee::withoutGlobalScopes()
            ->where('uuid', $resourceId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($employee === null) {
            return null;
        }

        return [
            'national_id' => $employee->national_id,
            'passport_number' => $employee->passport_number,
            'bank_account_number' => $employee->bank_account_number,
            'bank_iban' => $employee->bank_iban,
        ];
    }

    private function logAudit(
        int $userId,
        string $action,
        string $entityType,
        string $entityId,
        ?string $ipAddress
    ): void {
        try {
            $organizationId = auth('api')->user()?->organization_id;

            // Write to activity_logs (business event tracking), not audit_logs
            // (which is for model state-change diffs via HasAuditTrail).
            DB::table('activity_logs')->insert([
                'uuid'            => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'user_id'         => $userId,
                'action'          => $action,
                'entity_type'     => $entityType,
                'entity_id'       => $entityId,
                'description'     => "Sensitive data {$action} for {$entityType} {$entityId}",
                'module'          => 'core',
                'severity'        => 'warning',
                'ip_address'      => $ipAddress,
                'created_at'      => now(),
            ]);
        } catch (Throwable $e) {
            logger()->error('Activity log write failed for sensitive data access', [
                'user_id'    => $userId,
                'action'     => $action,
                'entity_type' => $entityType,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
