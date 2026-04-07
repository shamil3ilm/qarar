<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\AuditFinding;
use App\Models\Compliance\GrcCcmException;
use App\Models\Compliance\GrcCcmMonitor;
use App\Models\Compliance\GrcCsaQuestionnaire;
use App\Models\Compliance\SodViolation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CcmService
{
    public function createMonitor(int $organizationId, array $data, int $userId): GrcCcmMonitor
    {
        return GrcCcmMonitor::create(array_merge($data, [
            'organization_id' => $organizationId,
            'created_by'      => $userId,
            'owner_id'        => $data['owner_id'] ?? $userId,
        ]));
    }

    /**
     * Evaluate a monitor's rules against its data source and create exceptions.
     *
     * @return array{exceptions_detected: int, new_exceptions: int}
     */
    public function runMonitor(int $organizationId, string $monitorUuid): array
    {
        $monitor = GrcCcmMonitor::where('organization_id', $organizationId)
            ->where('uuid', $monitorUuid)
            ->firstOrFail();

        $exceptionsDetected = 0;
        $newExceptions      = 0;

        DB::transaction(function () use ($monitor, $organizationId, &$exceptionsDetected, &$newExceptions): void {
            $records = $this->fetchRecordsForDataSource($organizationId, $monitor->data_source);

            foreach ($records as $record) {
                $violations = $this->evaluateRules($monitor->rules, $record);

                if (!empty($violations)) {
                    $exceptionsDetected++;

                    $exists = GrcCcmException::where('monitor_id', $monitor->id)
                        ->where('record_type', $monitor->data_source)
                        ->where('record_id', $record['id'])
                        ->whereIn('status', [GrcCcmException::STATUS_OPEN, GrcCcmException::STATUS_ASSIGNED, GrcCcmException::STATUS_INVESTIGATED])
                        ->exists();

                    if (!$exists) {
                        GrcCcmException::create([
                            'organization_id'  => $organizationId,
                            'monitor_id'       => $monitor->id,
                            'record_type'      => $monitor->data_source,
                            'record_id'        => $record['id'],
                            'record_reference' => $record['reference'] ?? null,
                            'exception_details' => json_encode($violations, JSON_THROW_ON_ERROR),
                            'severity'         => $monitor->rules[0]['severity'] ?? GrcCcmException::SEVERITY_MEDIUM,
                            'status'           => GrcCcmException::STATUS_OPEN,
                            'detected_at'      => now(),
                        ]);
                        $newExceptions++;
                    }
                }
            }

            $monitor->update([
                'last_run_at'       => now(),
                'total_exceptions'  => DB::raw('total_exceptions + ' . $newExceptions),
            ]);
        });

        return [
            'exceptions_detected' => $exceptionsDetected,
            'new_exceptions'      => $newExceptions,
        ];
    }

    /**
     * Run all active monitors that are due according to their frequency.
     *
     * @return array{monitors_run: int, total_new_exceptions: int}
     */
    public function runAllDueMonitors(int $organizationId): array
    {
        $monitors = GrcCcmMonitor::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        $monitorsRun       = 0;
        $totalNewExceptions = 0;

        foreach ($monitors as $monitor) {
            if (!$monitor->isDue()) {
                continue;
            }

            try {
                $result = $this->runMonitor($organizationId, $monitor->uuid);
                $totalNewExceptions += $result['new_exceptions'];
                $monitorsRun++;
            } catch (\Throwable $e) {
                Log::error('CCM monitor run failed', [
                    'monitor_uuid' => $monitor->uuid,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return [
            'monitors_run'        => $monitorsRun,
            'total_new_exceptions' => $totalNewExceptions,
        ];
    }

    /**
     * @param array{monitor_id?: int, status?: string, severity?: string, detected_from?: string, detected_to?: string, per_page?: int} $filters
     */
    public function listExceptions(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = GrcCcmException::with(['monitor', 'assignee'])
            ->where('organization_id', $organizationId);

        if (!empty($filters['monitor_id'])) {
            $query->where('monitor_id', $filters['monitor_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['detected_from'])) {
            $query->where('detected_at', '>=', $filters['detected_from']);
        }

        if (!empty($filters['detected_to'])) {
            $query->where('detected_at', '<=', $filters['detected_to']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderByDesc('detected_at')->paginate($perPage);
    }

    public function resolveException(int $organizationId, string $uuid, array $data, int $userId): GrcCcmException
    {
        $exception = GrcCcmException::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $exception->update([
            'status'           => $data['status'] ?? GrcCcmException::STATUS_RESOLVED,
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'resolved_at'      => now(),
        ]);

        return $exception->fresh('monitor');
    }

    /**
     * Consolidated GRC dashboard across all sub-modules.
     *
     * @return array{
     *   findings: array{open: int, critical_overdue: int, avg_days_to_close: float},
     *   sod_violations: array{open: int, critical: int, high: int},
     *   ccm_exceptions: array{open_this_week: int, top_monitors: array},
     *   csa_completion: array{published: int, in_progress: int, overdue: int},
     *   risk_heatmap: array,
     * }
     */
    public function getGrcDashboard(int $organizationId): array
    {
        $today   = now()->toDateString();
        $weekAgo = now()->subWeek()->toDateString();

        // Findings summary
        $openFindings = AuditFinding::where('organization_id', $organizationId)
            ->whereNotIn('status', [AuditFinding::STATUS_CLOSED])
            ->count();

        $criticalOverdue = AuditFinding::where('organization_id', $organizationId)
            ->where('severity', AuditFinding::SEVERITY_CRITICAL)
            ->where('due_date', '<', $today)
            ->whereNotIn('status', [AuditFinding::STATUS_CLOSED, AuditFinding::STATUS_VERIFIED])
            ->count();

        $avgDaysToClose = AuditFinding::where('organization_id', $organizationId)
            ->where('status', AuditFinding::STATUS_CLOSED)
            ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
            ->value('avg_days') ?? 0.0;

        // SoD violations summary
        $sodOpen = SodViolation::where('organization_id', $organizationId)
            ->where('status', SodViolation::STATUS_OPEN)
            ->count();

        $sodCritical = SodViolation::where('organization_id', $organizationId)
            ->where('status', SodViolation::STATUS_OPEN)
            ->whereHas('conflict', fn ($q) => $q->where('risk_level', 'critical'))
            ->count();

        $sodHigh = SodViolation::where('organization_id', $organizationId)
            ->where('status', SodViolation::STATUS_OPEN)
            ->whereHas('conflict', fn ($q) => $q->where('risk_level', 'high'))
            ->count();

        // CCM exceptions summary
        $ccmOpenThisWeek = GrcCcmException::where('organization_id', $organizationId)
            ->where('status', GrcCcmException::STATUS_OPEN)
            ->where('detected_at', '>=', $weekAgo)
            ->count();

        $topMonitors = GrcCcmException::where('organization_id', $organizationId)
            ->where('status', GrcCcmException::STATUS_OPEN)
            ->select('monitor_id', DB::raw('count(*) as exception_count'))
            ->groupBy('monitor_id')
            ->orderByDesc('exception_count')
            ->limit(5)
            ->with('monitor:id,name,monitor_code')
            ->get()
            ->map(fn ($row) => [
                'monitor'         => $row->monitor?->only(['name', 'monitor_code']),
                'exception_count' => $row->exception_count,
            ])
            ->toArray();

        // CSA completion summary
        $csaPublished   = GrcCsaQuestionnaire::where('organization_id', $organizationId)->where('status', GrcCsaQuestionnaire::STATUS_PUBLISHED)->count();
        $csaInProgress  = GrcCsaQuestionnaire::where('organization_id', $organizationId)->where('status', GrcCsaQuestionnaire::STATUS_IN_PROGRESS)->count();
        $csaOverdue     = GrcCsaQuestionnaire::where('organization_id', $organizationId)
            ->whereNotIn('status', [GrcCsaQuestionnaire::STATUS_COMPLETED, GrcCsaQuestionnaire::STATUS_REVIEWED])
            ->where('due_date', '<', $today)
            ->count();

        // Risk heatmap: severity (rows) × status (cols) finding counts
        $heatmapData = AuditFinding::where('organization_id', $organizationId)
            ->whereNotIn('status', [AuditFinding::STATUS_CLOSED])
            ->select('severity', 'status', DB::raw('count(*) as total'))
            ->groupBy('severity', 'status')
            ->get();

        $riskHeatmap = [];
        foreach ($heatmapData as $row) {
            $riskHeatmap[$row->severity][$row->status] = $row->total;
        }

        return [
            'findings' => [
                'open'             => $openFindings,
                'critical_overdue' => $criticalOverdue,
                'avg_days_to_close' => round((float) $avgDaysToClose, 1),
            ],
            'sod_violations' => [
                'open'     => $sodOpen,
                'critical' => $sodCritical,
                'high'     => $sodHigh,
            ],
            'ccm_exceptions' => [
                'open_this_week' => $ccmOpenThisWeek,
                'top_monitors'   => $topMonitors,
            ],
            'csa_completion' => [
                'published'   => $csaPublished,
                'in_progress' => $csaInProgress,
                'overdue'     => $csaOverdue,
            ],
            'risk_heatmap' => $riskHeatmap,
        ];
    }

    /**
     * Fetch records from the configured data source for evaluation.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchRecordsForDataSource(int $organizationId, string $dataSource): array
    {
        $tableMap = [
            'invoices'        => 'invoices',
            'journal_entries' => 'journal_entries',
            'payments'        => 'payment_received',
            'purchase_orders' => 'purchase_orders',
            'bills'           => 'bills',
        ];

        $table = $tableMap[$dataSource] ?? null;

        if ($table === null || !Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', now()->subDay())
            ->select('id', 'created_at')
            ->limit(1000)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Evaluate rule set against a single record. Returns fired rule details.
     *
     * @param array<int, array{field: string, operator: string, value: mixed, severity?: string}> $rules
     * @param array<string, mixed> $record
     * @return array<int, array{rule: array, actual_value: mixed}>
     */
    private function evaluateRules(array $rules, array $record): array
    {
        $violations = [];

        foreach ($rules as $rule) {
            $field    = $rule['field'] ?? '';
            $operator = $rule['operator'] ?? '=';
            $expected = $rule['value'] ?? null;
            $actual   = $record[$field] ?? null;

            if ($this->ruleMatches($operator, $actual, $expected)) {
                $violations[] = [
                    'rule'         => $rule,
                    'actual_value' => $actual,
                ];
            }
        }

        return $violations;
    }

    private function ruleMatches(string $operator, mixed $actual, mixed $expected): bool
    {
        return match ($operator) {
            '>'         => is_numeric($actual) && (float) $actual > (float) $expected,
            '>='        => is_numeric($actual) && (float) $actual >= (float) $expected,
            '<'         => is_numeric($actual) && (float) $actual < (float) $expected,
            '<='        => is_numeric($actual) && (float) $actual <= (float) $expected,
            '!='        => $actual != $expected,
            'contains'  => is_string($actual) && str_contains($actual, (string) $expected),
            'is_null'   => $actual === null,
            'not_null'  => $actual !== null,
            default     => $actual == $expected, // '='
        };
    }
}
