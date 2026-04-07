<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\GrcKri;
use App\Models\Compliance\GrcRisk;
use App\Models\Compliance\GrcRiskCategory;
use App\Models\Compliance\GrcRiskReview;
use App\Models\Compliance\GrcRiskTreatment;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RiskManagementService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGen
    ) {}

    /**
     * @param array{risk_type?: string, risk_status?: string, min_residual_score?: int, per_page?: int} $filters
     */
    public function listRisks(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = GrcRisk::where('organization_id', $organizationId)
            ->with(['category', 'riskOwner', 'treatments']);

        if (!empty($filters['risk_type'])) {
            $query->where('risk_type', $filters['risk_type']);
        }

        if (!empty($filters['risk_status'])) {
            $query->where('risk_status', $filters['risk_status']);
        }

        if (isset($filters['min_residual_score'])) {
            $query->where('residual_score', '>=', (int) $filters['min_residual_score']);
        }

        return $query->latest()->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function createRisk(int $organizationId, array $data, int $userId): GrcRisk
    {
        return DB::transaction(function () use ($organizationId, $data, $userId): GrcRisk {
            $riskNumber = $this->numberGen->generate('RISK', null, $organizationId);

            $inherentLikelihood = (int) ($data['inherent_likelihood'] ?? 3);
            $inherentImpact     = (int) ($data['inherent_impact'] ?? 3);
            $residualLikelihood = (int) ($data['residual_likelihood'] ?? $inherentLikelihood);
            $residualImpact     = (int) ($data['residual_impact'] ?? $inherentImpact);

            return GrcRisk::create(array_merge($data, [
                'organization_id'    => $organizationId,
                'risk_number'        => $riskNumber,
                'risk_status'        => GrcRisk::STATUS_IDENTIFIED,
                'identified_date'    => $data['identified_date'] ?? now()->toDateString(),
                'created_by'         => $userId,
                'inherent_likelihood' => $inherentLikelihood,
                'inherent_impact'     => $inherentImpact,
                'inherent_score'      => $inherentLikelihood * $inherentImpact,
                'residual_likelihood' => $residualLikelihood,
                'residual_impact'     => $residualImpact,
                'residual_score'      => $residualLikelihood * $residualImpact,
            ]));
        });
    }

    public function findRisk(int $organizationId, string $uuid): GrcRisk
    {
        return GrcRisk::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->with(['category', 'riskOwner', 'treatments', 'reviews'])
            ->firstOrFail();
    }

    public function assessRisk(int $organizationId, string $uuid, array $data, int $userId): GrcRisk
    {
        $risk = GrcRisk::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $residualLikelihood = (int) $data['residual_likelihood'];
        $residualImpact     = (int) $data['residual_impact'];
        $inherentLikelihood = (int) $data['inherent_likelihood'];
        $inherentImpact     = (int) $data['inherent_impact'];

        $risk->update([
            'inherent_likelihood' => $inherentLikelihood,
            'inherent_impact'     => $inherentImpact,
            'inherent_score'      => $inherentLikelihood * $inherentImpact,
            'residual_likelihood' => $residualLikelihood,
            'residual_impact'     => $residualImpact,
            'residual_score'      => $residualLikelihood * $residualImpact,
            'existing_controls'   => $data['existing_controls'] ?? $risk->existing_controls,
            'risk_status'         => GrcRisk::STATUS_ASSESSED,
            'next_review_date'    => $data['next_review_date'] ?? null,
        ]);

        GrcRiskReview::create([
            'risk_id'             => $risk->id,
            'review_date'         => now()->toDateString(),
            'reviewed_likelihood' => $residualLikelihood,
            'reviewed_impact'     => $residualImpact,
            'notes'               => $data['review_notes'] ?? null,
            'reviewed_by'         => $userId,
        ]);

        return $risk->fresh(['category', 'treatments', 'reviews']);
    }

    public function addTreatment(int $organizationId, string $riskUuid, array $data, int $userId): GrcRiskTreatment
    {
        $risk = GrcRisk::where('organization_id', $organizationId)
            ->where('uuid', $riskUuid)
            ->firstOrFail();

        return GrcRiskTreatment::create(array_merge($data, [
            'organization_id' => $organizationId,
            'risk_id'         => $risk->id,
            'created_by'      => $userId,
        ]));
    }

    public function updateTreatment(int $organizationId, string $treatmentUuid, array $data): GrcRiskTreatment
    {
        $treatment = GrcRiskTreatment::where('organization_id', $organizationId)
            ->where('uuid', $treatmentUuid)
            ->firstOrFail();

        // Mark completed_date automatically when status transitions to completed
        if (($data['status'] ?? null) === GrcRiskTreatment::STATUS_COMPLETED && $treatment->completed_date === null) {
            $data['completed_date'] = $data['completed_date'] ?? now()->toDateString();
        }

        $treatment->update($data);

        return $treatment->fresh('risk');
    }

    public function getRiskHeatMap(int $organizationId): array
    {
        $risks = GrcRisk::where('organization_id', $organizationId)
            ->whereNotIn('risk_status', [GrcRisk::STATUS_CLOSED])
            ->select(['residual_likelihood', 'residual_impact', 'residual_score', 'risk_status', 'risk_type', 'title', 'uuid'])
            ->get();

        $matrix = [];
        for ($l = 1; $l <= 5; $l++) {
            for ($i = 1; $i <= 5; $i++) {
                $cell     = $risks->filter(fn (GrcRisk $r) => $r->residual_likelihood === $l && $r->residual_impact === $i);
                $score    = $l * $i;
                $matrix[$l][$i] = [
                    'count' => $cell->count(),
                    'score' => $score,
                    'zone'  => $this->riskZone($score),
                    'risks' => $cell->map(fn (GrcRisk $r) => [
                        'uuid'  => $r->uuid,
                        'title' => $r->title,
                    ])->values(),
                ];
            }
        }

        $summary = [
            'critical' => $risks->filter(fn (GrcRisk $r) => $r->residual_score >= 20)->count(),
            'high'     => $risks->filter(fn (GrcRisk $r) => $r->residual_score >= 12 && $r->residual_score < 20)->count(),
            'medium'   => $risks->filter(fn (GrcRisk $r) => $r->residual_score >= 6 && $r->residual_score < 12)->count(),
            'low'      => $risks->filter(fn (GrcRisk $r) => $r->residual_score < 6)->count(),
            'total'    => $risks->count(),
        ];

        return [
            'matrix'  => $matrix,
            'summary' => $summary,
            'by_type' => $risks->groupBy('risk_type')->map->count(),
        ];
    }

    /**
     * Dashboard using DB aggregation — no full-table loads.
     */
    public function getDashboard(int $organizationId): array
    {
        $today = now()->toDateString();

        // Risk summary: one aggregate query.
        $riskAgg = DB::table('grc_risks')
            ->where('organization_id', $organizationId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN residual_score >= 20 THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN next_review_date IS NOT NULL
                         AND next_review_date < ?
                         AND risk_status != ? THEN 1 ELSE 0 END) as overdue_review
            ', [$today, GrcRisk::STATUS_CLOSED])
            ->first();

        $byStatus = DB::table('grc_risks')
            ->where('organization_id', $organizationId)
            ->selectRaw('risk_status, COUNT(*) as cnt')
            ->groupBy('risk_status')
            ->pluck('cnt', 'risk_status');

        // KRI summary: one aggregate query.
        $kriAgg = DB::table('grc_kris')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN last_status = ? THEN 1 ELSE 0 END) as red,
                SUM(CASE WHEN last_status = ? THEN 1 ELSE 0 END) as amber,
                SUM(CASE WHEN last_status = ? THEN 1 ELSE 0 END) as green
            ', [GrcKri::STATUS_RED, GrcKri::STATUS_AMBER, GrcKri::STATUS_GREEN])
            ->first();

        return [
            'risk_summary' => [
                'total'          => (int) ($riskAgg->total ?? 0),
                'by_status'      => $byStatus,
                'critical_count' => (int) ($riskAgg->critical_count ?? 0),
                'overdue_review' => (int) ($riskAgg->overdue_review ?? 0),
            ],
            'kri_summary' => [
                'total' => (int) ($kriAgg->total ?? 0),
                'red'   => (int) ($kriAgg->red   ?? 0),
                'amber' => (int) ($kriAgg->amber  ?? 0),
                'green' => (int) ($kriAgg->green  ?? 0),
            ],
            'heat_map' => $this->getRiskHeatMap($organizationId),
        ];
    }

    public function listCategories(int $organizationId): \Illuminate\Database\Eloquent\Collection
    {
        return GrcRiskCategory::where('organization_id', $organizationId)
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    }

    public function createCategory(int $organizationId, array $data, int $userId): GrcRiskCategory
    {
        return GrcRiskCategory::create(array_merge($data, [
            'organization_id' => $organizationId,
        ]));
    }

    private function riskZone(int $score): string
    {
        return match (true) {
            $score >= 20 => 'critical',
            $score >= 12 => 'high',
            $score >= 6  => 'medium',
            default      => 'low',
        };
    }
}
