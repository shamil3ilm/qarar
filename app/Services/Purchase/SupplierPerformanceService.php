<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\SupplierDeliveryRecord;
use App\Models\Purchase\SupplierEvaluationCriteria;
use App\Models\Purchase\SupplierIncident;
use App\Models\Purchase\SupplierScorecard;
use App\Models\Purchase\SupplierScorecardRating;
use Illuminate\Support\Facades\DB;

class SupplierPerformanceService
{
    // -------------------------------------------------------------------------
    // Evaluation Criteria
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     */
    public function createCriteria(int $orgId, array $data, int $userId): SupplierEvaluationCriteria
    {
        return SupplierEvaluationCriteria::create(array_merge(
            $data,
            ['organization_id' => $orgId]
        ));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCriteria(SupplierEvaluationCriteria $criteria, array $data, int $userId): SupplierEvaluationCriteria
    {
        $criteria->update($data);

        return $criteria->fresh();
    }

    // -------------------------------------------------------------------------
    // Scorecards
    // -------------------------------------------------------------------------

    /**
     * Create a scorecard with optional ratings.
     *
     * @param array<string, mixed> $data
     */
    public function createScorecard(int $orgId, array $data, int $userId): SupplierScorecard
    {
        return DB::transaction(function () use ($orgId, $data, $userId): SupplierScorecard {
            $scorecard = SupplierScorecard::create([
                'organization_id'         => $orgId,
                'supplier_id'             => $data['supplier_id'],
                'evaluation_period_start' => $data['evaluation_period_start'],
                'evaluation_period_end'   => $data['evaluation_period_end'],
                'notes'                   => $data['notes'] ?? null,
                'status'                  => SupplierScorecard::STATUS_DRAFT,
                'created_by'              => $userId,
            ]);

            foreach ($data['ratings'] ?? [] as $rating) {
                SupplierScorecardRating::create([
                    'scorecard_id' => $scorecard->id,
                    'criterion_id' => $rating['criterion_id'],
                    'score'        => $rating['score'],
                    'comments'     => $rating['comments'] ?? null,
                ]);
            }

            return $scorecard->load(['ratings.criterion', 'supplier']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateScorecard(SupplierScorecard $scorecard, array $data, int $userId): SupplierScorecard
    {
        return DB::transaction(function () use ($scorecard, $data): SupplierScorecard {
            $fields = array_filter([
                'supplier_id'             => $data['supplier_id'] ?? null,
                'evaluation_period_start' => $data['evaluation_period_start'] ?? null,
                'evaluation_period_end'   => $data['evaluation_period_end'] ?? null,
                'notes'                   => $data['notes'] ?? null,
            ], fn($v) => $v !== null);

            if (!empty($fields)) {
                $scorecard->update($fields);
            }

            if (!empty($data['ratings'])) {
                $scorecard->ratings()->delete();

                foreach ($data['ratings'] as $rating) {
                    SupplierScorecardRating::create([
                        'scorecard_id' => $scorecard->id,
                        'criterion_id' => $rating['criterion_id'],
                        'score'        => $rating['score'],
                        'comments'     => $rating['comments'] ?? null,
                    ]);
                }
            }

            return $scorecard->fresh(['ratings.criterion', 'supplier']);
        });
    }

    /**
     * Finalize a scorecard: calculate per-category and overall scores.
     */
    public function finalizeScorecard(SupplierScorecard $scorecard, int $userId): SupplierScorecard
    {
        if ($scorecard->isFinalized()) {
            throw new \LogicException('Scorecard is already finalized.');
        }

        return DB::transaction(fn(): SupplierScorecard => $scorecard->finalize($userId));
    }

    // -------------------------------------------------------------------------
    // Delivery Records
    // -------------------------------------------------------------------------

    /**
     * Record a delivery event, auto-calculating on-time and completeness flags.
     *
     * @param array<string, mixed> $data
     */
    public function recordDelivery(int $orgId, array $data, int $userId): SupplierDeliveryRecord
    {
        return DB::transaction(function () use ($orgId, $data): SupplierDeliveryRecord {
            $promisedDate = $data['promised_date'];
            $actualDate   = $data['actual_date'] ?? null;
            $qtyOrdered   = (float) $data['quantity_ordered'];
            $qtyReceived  = (float) ($data['quantity_received'] ?? 0);
            $defectQty    = (float) ($data['defect_quantity'] ?? 0);

            $isOnTime   = $actualDate !== null ? ($actualDate <= $promisedDate) : null;
            $isComplete = $qtyOrdered > 0 ? ($qtyReceived >= $qtyOrdered) : null;

            return SupplierDeliveryRecord::create([
                'organization_id'   => $orgId,
                'purchase_order_id' => $data['purchase_order_id'],
                'supplier_id'       => $data['supplier_id'],
                'promised_date'     => $promisedDate,
                'actual_date'       => $actualDate,
                'quantity_ordered'  => $qtyOrdered,
                'quantity_received' => $qtyReceived,
                'is_on_time'        => $isOnTime,
                'is_complete'       => $isComplete,
                'quality_accepted'  => $data['quality_accepted'] ?? null,
                'defect_quantity'   => $defectQty,
                'notes'             => $data['notes'] ?? null,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Incidents
    // -------------------------------------------------------------------------

    /**
     * Record a new supplier incident.
     *
     * @param array<string, mixed> $data
     */
    public function createIncident(int $orgId, array $data, int $userId): SupplierIncident
    {
        return DB::transaction(function () use ($orgId, $data, $userId): SupplierIncident {
            return SupplierIncident::create([
                'organization_id' => $orgId,
                'supplier_id'     => $data['supplier_id'],
                'incident_type'   => $data['incident_type'],
                'severity'        => $data['severity'],
                'description'     => $data['description'],
                'occurred_at'     => $data['occurred_at'],
                'created_by'      => $userId,
            ]);
        });
    }

    /**
     * Resolve an open incident.
     */
    public function resolveIncident(
        SupplierIncident $incident,
        string $resolutionNotes,
        int $userId
    ): SupplierIncident {
        if ($incident->isResolved()) {
            throw new \LogicException('Incident is already resolved.');
        }

        return DB::transaction(function () use ($incident, $resolutionNotes): SupplierIncident {
            $incident->update([
                'resolved_at'      => now()->toDateString(),
                'resolution_notes' => $resolutionNotes,
            ]);

            return $incident->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Analytics
    // -------------------------------------------------------------------------

    /**
     * Compute stats for a supplier within an organisation.
     *
     * @return array<string, mixed>
     */
    public function getSupplierStats(int $orgId, int $supplierId): array
    {
        $deliveries = SupplierDeliveryRecord::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('supplier_id', $supplierId)
            ->get();

        $totalOrders   = $deliveries->count();
        $onTimeCount   = $deliveries->where('is_on_time', true)->count();
        $completeCount = $deliveries->where('is_complete', true)->count();
        $totalReceived = (float) $deliveries->sum('quantity_received');
        $totalDefect   = (float) $deliveries->sum('defect_quantity');

        $openIncidents = SupplierIncident::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('supplier_id', $supplierId)
            ->whereNull('resolved_at')
            ->count();

        $avgScore = SupplierScorecard::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('supplier_id', $supplierId)
            ->where('status', SupplierScorecard::STATUS_FINALIZED)
            ->whereNotNull('overall_score')
            ->avg('overall_score');

        $onTimeRate = $totalOrders > 0
            ? (float) bcmul(bcdiv((string) $onTimeCount, (string) $totalOrders, 6), '100', 2)
            : null;

        $completeRate = $totalOrders > 0
            ? (float) bcmul(bcdiv((string) $completeCount, (string) $totalOrders, 6), '100', 2)
            : null;

        $defectRate = $totalReceived > 0
            ? (float) bcmul(bcdiv((string) $totalDefect, (string) $totalReceived, 6), '100', 2)
            : null;

        return [
            'supplier_id'    => $supplierId,
            'total_orders'   => $totalOrders,
            'on_time_rate'   => $onTimeRate,
            'complete_rate'  => $completeRate,
            'defect_rate'    => $defectRate,
            'open_incidents' => $openIncidents,
            'avg_score'      => $avgScore !== null ? round((float) $avgScore, 2) : null,
        ];
    }

    /**
     * Rank all suppliers in an org by avg overall_score from finalized scorecards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSupplierRanking(int $orgId): array
    {
        $rows = SupplierScorecard::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('status', SupplierScorecard::STATUS_FINALIZED)
            ->whereNotNull('overall_score')
            ->selectRaw('supplier_id, AVG(overall_score) as avg_score, COUNT(*) as scorecard_count')
            ->groupBy('supplier_id')
            ->orderByDesc('avg_score')
            ->get();

        return $rows->values()->map(function ($row, int $idx): array {
            return [
                'rank'            => $idx + 1,
                'supplier_id'     => $row->supplier_id,
                'avg_score'       => round((float) $row->avg_score, 2),
                'scorecard_count' => (int) $row->scorecard_count,
            ];
        })->all();
    }
}
