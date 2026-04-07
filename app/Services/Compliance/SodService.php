<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\SodConflict;
use App\Models\Compliance\SodFunction;
use App\Models\Compliance\SodViolation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SodService
{
    /**
     * Review a single user for SoD violations.
     *
     * Gets all permissions via roles, maps to SoD functions, then checks
     * the conflict matrix. Returns detected violations (not persisted).
     *
     * @return array{violations: array<int, array{conflict: SodConflict, function_a: SodFunction, function_b: SodFunction, risk_level: string}>}
     */
    public function runUserAccessReview(int $organizationId, int $userId): array
    {
        $user = User::findOrFail($userId);

        $userPermissions = $this->getUserPermissions($user);

        $activeFunctions = SodFunction::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->limit(500)
            ->get();

        // Map user permissions to SoD functions
        $userFunctionIds = $activeFunctions->filter(function (SodFunction $fn) use ($userPermissions): bool {
            if (!is_array($fn->permissions)) {
                return false;
            }

            return count(array_intersect($fn->permissions, $userPermissions)) > 0;
        })->pluck('id')->toArray();

        if (count($userFunctionIds) < 2) {
            return ['violations' => []];
        }

        $conflicts = SodConflict::with(['functionA', 'functionB'])
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where(function ($q) use ($userFunctionIds): void {
                $q->whereIn('function_a_id', $userFunctionIds)
                    ->whereIn('function_b_id', $userFunctionIds);
            })
            ->get();

        $violations = $conflicts->map(fn (SodConflict $conflict) => [
            'conflict'   => $conflict,
            'function_a' => $conflict->functionA,
            'function_b' => $conflict->functionB,
            'risk_level' => $conflict->risk_level,
        ])->values()->toArray();

        return ['violations' => $violations];
    }

    /**
     * Scan all active users in the organization, upsert violations.
     *
     * @return array{total_users_scanned: int, violations_found: int, critical_count: int, high_count: int}
     */
    public function runOrganizationScan(int $organizationId): array
    {
        $totalUsersScanned    = 0;
        $totalViolationsFound = 0;
        $criticalCount        = 0;
        $highCount            = 0;

        User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->chunkById(50, function ($users) use ($organizationId, &$totalUsersScanned, &$totalViolationsFound, &$criticalCount, &$highCount): void {
                DB::transaction(function () use ($users, $organizationId, &$totalUsersScanned, &$totalViolationsFound, &$criticalCount, &$highCount): void {
                    foreach ($users as $user) {
                        $review = $this->runUserAccessReview($organizationId, $user->id);

                        foreach ($review['violations'] as $violation) {
                            /** @var SodConflict $conflict */
                            $conflict = $violation['conflict'];

                            SodViolation::updateOrCreate(
                                [
                                    'organization_id' => $organizationId,
                                    'conflict_id'     => $conflict->id,
                                    'user_id'         => $user->id,
                                ],
                                [
                                    'status'      => SodViolation::STATUS_OPEN,
                                    'detected_at' => now(),
                                ]
                            );

                            $totalViolationsFound++;

                            if ($conflict->risk_level === SodConflict::RISK_CRITICAL) {
                                $criticalCount++;
                            } elseif ($conflict->risk_level === SodConflict::RISK_HIGH) {
                                $highCount++;
                            }
                        }

                        $totalUsersScanned++;
                    }
                });
            });

        return [
            'total_users_scanned' => $totalUsersScanned,
            'violations_found'    => $totalViolationsFound,
            'critical_count'      => $criticalCount,
            'high_count'          => $highCount,
        ];
    }

    /**
     * @param array{user_id?: int, status?: string, risk_level?: string, per_page?: int} $filters
     */
    public function listViolations(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = SodViolation::with(['conflict.functionA', 'conflict.functionB', 'user', 'acceptedBy'])
            ->where('organization_id', $organizationId);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['risk_level'])) {
            $query->whereHas('conflict', function ($q) use ($filters): void {
                $q->where('risk_level', $filters['risk_level']);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderByDesc('detected_at')->paginate($perPage);
    }

    public function acceptRisk(int $organizationId, string $uuid, array $data, int $userId): SodViolation
    {
        $violation = SodViolation::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $violation->update([
            'status'                  => SodViolation::STATUS_RISK_ACCEPTED,
            'mitigation_description'  => $data['mitigation_description'] ?? null,
            'review_date'             => $data['review_date'] ?? null,
            'accepted_by'             => $userId,
            'accepted_at'             => now(),
        ]);

        return $violation->fresh(['conflict', 'user', 'acceptedBy']);
    }

    public function listConflicts(int $organizationId): Collection
    {
        return SodConflict::with(['functionA', 'functionB'])
            ->where('organization_id', $organizationId)
            ->orderBy('risk_level')
            ->limit(500)
            ->get();
    }

    public function createConflict(int $organizationId, array $data): SodConflict
    {
        return SodConflict::create(array_merge($data, [
            'organization_id' => $organizationId,
        ]));
    }

    public function createFunction(int $organizationId, array $data): SodFunction
    {
        return SodFunction::create(array_merge($data, [
            'organization_id' => $organizationId,
        ]));
    }

    /**
     * Retrieve all permission codes assigned to a user via their roles.
     *
     * @return string[]
     */
    private function getUserPermissions(User $user): array
    {
        if (!method_exists($user, 'roles')) {
            return [];
        }

        $permissions = [];

        foreach ($user->roles ?? [] as $role) {
            foreach ($role->permissions ?? [] as $permission) {
                $permissions[] = is_string($permission) ? $permission : ($permission->name ?? '');
            }
        }

        return array_values(array_unique(array_filter($permissions)));
    }
}
