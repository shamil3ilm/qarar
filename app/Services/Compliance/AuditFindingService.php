<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\AuditEngagement;
use App\Models\Compliance\AuditFinding;
use App\Models\Compliance\FindingAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AuditFindingService
{
    /**
     * List findings with optional filters.
     *
     * @param array{
     *   status?: string,
     *   severity?: string,
     *   due_date_from?: string,
     *   due_date_to?: string,
     *   overdue?: bool,
     *   engagement_id?: int,
     *   per_page?: int,
     * } $filters
     */
    public function listFindings(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = AuditFinding::with(['engagement', 'owner', 'actions'])
            ->where('organization_id', $organizationId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['due_date_from'])) {
            $query->where('due_date', '>=', $filters['due_date_from']);
        }

        if (!empty($filters['due_date_to'])) {
            $query->where('due_date', '<=', $filters['due_date_to']);
        }

        if (!empty($filters['overdue'])) {
            $query->where('due_date', '<', now()->toDateString())
                ->whereNotIn('status', [AuditFinding::STATUS_CLOSED, AuditFinding::STATUS_VERIFIED]);
        }

        if (!empty($filters['engagement_id'])) {
            $query->where('engagement_id', $filters['engagement_id']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function createEngagement(int $organizationId, array $data, int $userId): AuditEngagement
    {
        return DB::transaction(function () use ($organizationId, $data, $userId): AuditEngagement {
            $number = $this->generateEngagementNumber($organizationId);

            return AuditEngagement::create(array_merge($data, [
                'organization_id'   => $organizationId,
                'engagement_number' => $number,
                'created_by'        => $userId,
                'status'            => AuditEngagement::STATUS_PLANNING,
            ]));
        });
    }

    public function createFinding(int $organizationId, array $data, int $userId): AuditFinding
    {
        return DB::transaction(function () use ($organizationId, $data, $userId): AuditFinding {
            $number = $this->generateFindingNumber($organizationId);

            $finding = AuditFinding::create(array_merge($data, [
                'organization_id' => $organizationId,
                'finding_number'  => $number,
                'status'          => AuditFinding::STATUS_OPEN,
                'created_by'      => $userId,
            ]));

            if (!empty($data['actions']) && is_array($data['actions'])) {
                foreach ($data['actions'] as $actionData) {
                    FindingAction::create(array_merge($actionData, [
                        'finding_id' => $finding->id,
                        'created_by' => $userId,
                        'status'     => FindingAction::STATUS_OPEN,
                    ]));
                }
            }

            return $finding->load(['engagement', 'owner', 'actions']);
        });
    }

    public function assignFinding(int $organizationId, string $uuid, int $ownerId, array $data): AuditFinding
    {
        $finding = AuditFinding::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $finding->update([
            'status'                   => AuditFinding::STATUS_ASSIGNED,
            'owner_id'                 => $ownerId,
            'remediation_target_date'  => $data['remediation_target_date'] ?? null,
            'remediation_plan'         => $data['remediation_plan'] ?? null,
        ]);

        return $finding->fresh(['owner', 'actions']);
    }

    public function submitRemediation(int $organizationId, string $uuid, array $data, int $userId): AuditFinding
    {
        $finding = AuditFinding::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $finding->update([
            'status'                       => AuditFinding::STATUS_REMEDIATED,
            'remediation_completed_date'   => now()->toDateString(),
            'remediation_plan'             => $data['remediation_plan'] ?? $finding->remediation_plan,
        ]);

        return $finding->fresh(['owner', 'actions']);
    }

    public function verifyRemediation(int $organizationId, string $uuid, array $data, int $userId): AuditFinding
    {
        $finding = AuditFinding::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $finding->update([
            'status'             => AuditFinding::STATUS_VERIFIED,
            'verified_by'        => $userId,
            'verified_at'        => now(),
            'verification_notes' => $data['verification_notes'] ?? null,
        ]);

        return $finding->fresh(['verifier', 'owner', 'actions']);
    }

    public function closeFinding(int $organizationId, string $uuid, int $userId): AuditFinding
    {
        $finding = AuditFinding::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $finding->update(['status' => AuditFinding::STATUS_CLOSED]);

        return $finding->fresh(['owner', 'actions']);
    }

    public function getDashboard(int $organizationId): array
    {
        $now      = now();
        $today    = $now->toDateString();
        $in7Days  = $now->copy()->addDays(7)->toDateString();
        $thirtyDaysAgo = $now->copy()->subDays(30)->toDateString();

        $bySeverity = AuditFinding::where('organization_id', $organizationId)
            ->whereNotIn('status', [AuditFinding::STATUS_CLOSED])
            ->select('severity', DB::raw('count(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->toArray();

        $byStatus = AuditFinding::where('organization_id', $organizationId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $overdueCount = AuditFinding::where('organization_id', $organizationId)
            ->where('due_date', '<', $today)
            ->whereNotIn('status', [AuditFinding::STATUS_CLOSED, AuditFinding::STATUS_VERIFIED])
            ->count();

        $upcoming = AuditFinding::where('organization_id', $organizationId)
            ->whereBetween('due_date', [$today, $in7Days])
            ->whereNotIn('status', [AuditFinding::STATUS_CLOSED, AuditFinding::STATUS_VERIFIED])
            ->count();

        $openedTrend = AuditFinding::where('organization_id', $organizationId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as opened'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->map(fn ($row) => $row->opened)
            ->toArray();

        $closedTrend = AuditFinding::where('organization_id', $organizationId)
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where('status', AuditFinding::STATUS_CLOSED)
            ->select(DB::raw('DATE(updated_at) as date'), DB::raw('count(*) as closed'))
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->map(fn ($row) => $row->closed)
            ->toArray();

        return [
            'by_severity'    => $bySeverity,
            'by_status'      => $byStatus,
            'overdue_count'  => $overdueCount,
            'upcoming_count' => $upcoming,
            'trend'          => [
                'opened' => $openedTrend,
                'closed' => $closedTrend,
            ],
        ];
    }

    private function generateEngagementNumber(int $organizationId): string
    {
        $count = AuditEngagement::where('organization_id', $organizationId)->withTrashed()->count();

        return 'AE-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }

    private function generateFindingNumber(int $organizationId): string
    {
        $count = AuditFinding::where('organization_id', $organizationId)->withTrashed()->count();

        return 'AF-' . str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
