<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\PriceOverride;
use App\Models\Sales\PriceOverridePolicy;
use Illuminate\Support\Facades\DB;

class PriceOverrideService
{
    public function getPolicies(int $organizationId): mixed
    {
        return PriceOverridePolicy::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();
    }

    public function createPolicy(array $data): PriceOverridePolicy
    {
        return PriceOverridePolicy::create($data);
    }

    public function validateOverride(int $userId, int $productId, float $originalPrice, float $overridePrice): array
    {
        if (bccomp((string) $originalPrice, '0', 4) <= 0) {
            return ['allowed' => false, 'requires_approval' => false, 'discount_percent' => '0.0000'];
        }

        if (bccomp((string) $overridePrice, '0', 4) < 0) {
            return ['allowed' => false, 'requires_approval' => false, 'discount_percent' => '0.0000'];
        }

        $discountPercent = bcmul(
            bcdiv(bcsub((string) $originalPrice, (string) $overridePrice, 4), (string) $originalPrice, 6),
            '100',
            4
        );

        $policies = PriceOverridePolicy::where('is_active', true)->get();
        $requiresApproval = false;
        $allowed = true;

        foreach ($policies as $policy) {
            if (bccomp($discountPercent, '0', 4) > 0 && !$policy->allow_discount) {
                $allowed = false;
                break;
            }
            if (bccomp($discountPercent, '0', 4) < 0 && !$policy->allow_markup) {
                $allowed = false;
                break;
            }
            if ($policy->max_discount_percent && bccomp($discountPercent, (string) $policy->max_discount_percent, 4) > 0) {
                $allowed = false;
                break;
            }
            if ($policy->requires_approval && $policy->approval_threshold_percent && bccomp($discountPercent, (string) $policy->approval_threshold_percent, 4) > 0) {
                $requiresApproval = true;
            }
        }

        return [
            'allowed' => $allowed,
            'requires_approval' => $requiresApproval,
            'discount_percent' => $discountPercent,
        ];
    }

    public function recordOverride(array $data): PriceOverride
    {
        return PriceOverride::create($data);
    }

    public function approveOverride(int $overrideId, int $userId, ?string $notes = null): PriceOverride
    {
        $override = PriceOverride::findOrFail($overrideId);
        $override->update([
            'approval_status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);

        return $override->fresh();
    }

    public function rejectOverride(int $overrideId, int $userId, ?string $notes = null): PriceOverride
    {
        $override = PriceOverride::findOrFail($overrideId);
        $override->update([
            'approval_status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);

        return $override->fresh();
    }

    public function getOverrideReport(int $organizationId, array $filters = []): array
    {
        $query = PriceOverride::where('organization_id', $organizationId);

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return [
            'total_overrides' => $query->count(),
            'total_impact' => (float) $query->sum('total_impact'),
            'by_type' => $query->select('override_type', DB::raw('count(*) as count'), DB::raw('sum(total_impact) as impact'))
                ->groupBy('override_type')
                ->get()
                ->toArray(),
        ];
    }
}
