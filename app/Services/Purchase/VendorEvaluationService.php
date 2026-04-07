<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\SupplierEvaluationCriteria;
use App\Models\Purchase\SupplierScorecard;
use App\Models\Purchase\SupplierScorecardRating;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Vendor / Supplier Evaluation — SAP MM ME61 equivalent.
 *
 * Manages the full lifecycle:
 *  1. Define evaluation criteria (quality, delivery, price, service, compliance)
 *  2. Create a draft scorecard for a supplier and period
 *  3. Submit per-criterion ratings (0–100 scale)
 *  4. Finalize — computes weighted overall score, locks the scorecard
 *  5. Reporting — supplier ranking, trend, category breakdown
 */
class VendorEvaluationService
{
    // ----------------------------------------------------------------
    // Criteria
    // ----------------------------------------------------------------

    public function createCriterion(int $organizationId, array $data): SupplierEvaluationCriteria
    {
        return SupplierEvaluationCriteria::create([
            'organization_id' => $organizationId,
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'category'        => $data['category'],
            'weight_percent'  => $data['weight_percent'],
            'is_active'       => true,
        ]);
    }

    public function getCriteria(int $organizationId): \Illuminate\Database\Eloquent\Collection
    {
        return SupplierEvaluationCriteria::where('organization_id', $organizationId)
            ->active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    // ----------------------------------------------------------------
    // Scorecard lifecycle
    // ----------------------------------------------------------------

    /**
     * Create a draft scorecard with empty rating rows for all active criteria.
     */
    public function createScorecard(
        int $organizationId,
        int $supplierId,
        string $periodStart,
        string $periodEnd,
        int $createdBy,
    ): SupplierScorecard {
        return DB::transaction(function () use ($organizationId, $supplierId, $periodStart, $periodEnd, $createdBy): SupplierScorecard {
            $scorecard = SupplierScorecard::create([
                'organization_id'        => $organizationId,
                'supplier_id'            => $supplierId,
                'evaluation_period_start' => $periodStart,
                'evaluation_period_end'  => $periodEnd,
                'status'                 => SupplierScorecard::STATUS_DRAFT,
                'created_by'             => $createdBy,
            ]);

            // Pre-populate rating rows for each active criterion
            $criteria = SupplierEvaluationCriteria::where('organization_id', $organizationId)
                ->active()
                ->get();

            foreach ($criteria as $criterion) {
                SupplierScorecardRating::create([
                    'scorecard_id' => $scorecard->id,
                    'criterion_id' => $criterion->id,
                    'score'        => 0,
                    'comments'     => null,
                ]);
            }

            return $scorecard->load('ratings.criterion');
        });
    }

    /**
     * Update individual criterion ratings on a draft scorecard.
     *
     * @param  array<int, array{criterion_id: int, score: float, comments?: string}>  $ratings
     */
    public function updateRatings(SupplierScorecard $scorecard, array $ratings): SupplierScorecard
    {
        if (! $scorecard->isDraft()) {
            throw new \RuntimeException('Only draft scorecards can be updated.');
        }

        DB::transaction(function () use ($scorecard, $ratings): void {
            foreach ($ratings as $rating) {
                SupplierScorecardRating::where('scorecard_id', $scorecard->id)
                    ->where('criterion_id', $rating['criterion_id'])
                    ->update([
                        'score'    => $rating['score'],
                        'comments' => $rating['comments'] ?? null,
                    ]);
            }
        });

        return $scorecard->fresh('ratings.criterion');
    }

    /**
     * Finalize scorecard — compute weighted scores and lock.
     */
    public function finalize(SupplierScorecard $scorecard, int $userId): SupplierScorecard
    {
        if (! $scorecard->isDraft()) {
            throw new \RuntimeException('Scorecard is already finalized.');
        }

        return $scorecard->finalize($userId);
    }

    // ----------------------------------------------------------------
    // Reporting
    // ----------------------------------------------------------------

    /**
     * Paginated list of scorecards for an organization.
     */
    public function listScorecards(
        int $organizationId,
        array $filters = [],
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = SupplierScorecard::where('organization_id', $organizationId)
            ->with('supplier:id,name')
            ->orderByDesc('evaluation_period_end');

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Supplier ranking — sorted by overall_score desc for a given period.
     */
    public function supplierRanking(int $organizationId, string $periodStart, string $periodEnd): array
    {
        return SupplierScorecard::where('organization_id', $organizationId)
            ->where('status', SupplierScorecard::STATUS_FINALIZED)
            ->whereBetween('evaluation_period_end', [$periodStart, $periodEnd])
            ->with('supplier:id,name')
            ->orderByDesc('overall_score')
            ->get()
            ->map(fn (SupplierScorecard $sc, int $rank) => [
                'rank'            => $rank + 1,
                'supplier_id'     => $sc->supplier_id,
                'supplier_name'   => $sc->supplier?->name,
                'overall_score'   => $sc->overall_score,
                'quality_score'   => $sc->quality_score,
                'delivery_score'  => $sc->delivery_score,
                'price_score'     => $sc->price_score,
                'service_score'   => $sc->service_score,
                'compliance_score' => $sc->compliance_score,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Score trend for a specific supplier across multiple periods.
     */
    public function supplierTrend(int $organizationId, int $supplierId): array
    {
        return SupplierScorecard::where('organization_id', $organizationId)
            ->where('supplier_id', $supplierId)
            ->where('status', SupplierScorecard::STATUS_FINALIZED)
            ->orderBy('evaluation_period_end')
            ->get()
            ->map(fn (SupplierScorecard $sc) => [
                'period_end'      => $sc->evaluation_period_end,
                'overall_score'   => $sc->overall_score,
                'quality_score'   => $sc->quality_score,
                'delivery_score'  => $sc->delivery_score,
                'price_score'     => $sc->price_score,
            ])
            ->toArray();
    }
}
