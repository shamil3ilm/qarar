<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\QmDynamicModificationRule;
use App\Models\Manufacturing\QmInspectionStageLog;
use Illuminate\Support\Facades\DB;

class DynamicModificationService
{
    /**
     * Sample size multipliers per stage.
     */
    private const SAMPLE_MODIFIERS = [
        QmInspectionStageLog::STAGE_TIGHTENED => 1.5,
        QmInspectionStageLog::STAGE_NORMAL    => 1.0,
        QmInspectionStageLog::STAGE_REDUCED   => 0.4,
        QmInspectionStageLog::STAGE_SKIP      => 0.0,
    ];

    /**
     * Evaluate an inspection result, transition stage if necessary, and
     * return the new stage with the recommended sample size modifier.
     *
     * @return array{current_stage: string, previous_stage: string, recommended_sample_modifier: float, consecutive_pass: int, consecutive_fail: int}
     */
    public function evaluateInspectionResult(
        int $organizationId,
        int $ruleId,
        int $productId,
        ?int $supplierId,
        bool $passed,
    ): array {
        return DB::transaction(function () use ($organizationId, $ruleId, $productId, $supplierId, $passed): array {
            $rule = QmDynamicModificationRule::where('organization_id', $organizationId)
                ->where('id', $ruleId)
                ->lockForUpdate()
                ->firstOrFail();

            $log = QmInspectionStageLog::where('organization_id', $organizationId)
                ->where('rule_id', $ruleId)
                ->where('product_id', $productId)
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->firstOrNew([
                    'organization_id' => $organizationId,
                    'rule_id'         => $ruleId,
                    'product_id'      => $productId,
                    'supplier_id'     => $supplierId,
                    'current_stage'   => QmInspectionStageLog::STAGE_NORMAL,
                    'consecutive_pass' => 0,
                    'consecutive_fail' => 0,
                ]);

            $previousStage = $log->current_stage ?? QmInspectionStageLog::STAGE_NORMAL;

            if ($passed) {
                $log->consecutive_pass = ($log->consecutive_pass ?? 0) + 1;
                $log->consecutive_fail = 0;
            } else {
                $log->consecutive_fail = ($log->consecutive_fail ?? 0) + 1;
                $log->consecutive_pass = 0;
            }

            $newStage = $this->resolveStageTransition($rule, $log, $previousStage);
            $log->current_stage     = $newStage;
            $log->last_evaluated_at = now();
            $log->save();

            return [
                'current_stage'              => $newStage,
                'previous_stage'             => $previousStage,
                'recommended_sample_modifier' => self::SAMPLE_MODIFIERS[$newStage],
                'consecutive_pass'           => $log->consecutive_pass,
                'consecutive_fail'           => $log->consecutive_fail,
            ];
        });
    }

    public function getCurrentStage(
        int $organizationId,
        int $ruleId,
        int $productId,
        ?int $supplierId,
    ): ?QmInspectionStageLog {
        return QmInspectionStageLog::where('organization_id', $organizationId)
            ->where('rule_id', $ruleId)
            ->where('product_id', $productId)
            ->where('supplier_id', $supplierId)
            ->first();
    }

    public function listRules(int $organizationId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = QmDynamicModificationRule::where('organization_id', $organizationId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->latest()->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function createRule(int $organizationId, array $data, int $userId): QmDynamicModificationRule
    {
        return QmDynamicModificationRule::create([
            ...$data,
            'organization_id' => $organizationId,
            'created_by'      => $userId,
        ]);
    }

    public function findRule(int $organizationId, string $uuid): QmDynamicModificationRule
    {
        return QmDynamicModificationRule::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveStageTransition(
        QmDynamicModificationRule $rule,
        QmInspectionStageLog $log,
        string $currentStage,
    ): string {
        return match ($currentStage) {
            QmInspectionStageLog::STAGE_NORMAL => $this->transitionFromNormal($rule, $log),
            QmInspectionStageLog::STAGE_TIGHTENED => $this->transitionFromTightened($rule, $log),
            QmInspectionStageLog::STAGE_REDUCED => $this->transitionFromReduced($rule, $log),
            // Skip: any failure reinstates normal
            QmInspectionStageLog::STAGE_SKIP => $log->consecutive_fail > 0
                ? QmInspectionStageLog::STAGE_NORMAL
                : QmInspectionStageLog::STAGE_SKIP,
            default => $currentStage,
        };
    }

    private function transitionFromNormal(QmDynamicModificationRule $rule, QmInspectionStageLog $log): string
    {
        if ($log->consecutive_fail >= $rule->tighten_consecutive_fails) {
            return QmInspectionStageLog::STAGE_TIGHTENED;
        }

        if ($log->consecutive_pass >= $rule->reduce_after_consecutive_pass) {
            return QmInspectionStageLog::STAGE_REDUCED;
        }

        return QmInspectionStageLog::STAGE_NORMAL;
    }

    private function transitionFromTightened(QmDynamicModificationRule $rule, QmInspectionStageLog $log): string
    {
        // Reinstate to normal after enough consecutive passes while tightened
        if ($log->consecutive_pass >= $rule->reinstate_after_tightened_fail) {
            return QmInspectionStageLog::STAGE_NORMAL;
        }

        return QmInspectionStageLog::STAGE_TIGHTENED;
    }

    private function transitionFromReduced(QmDynamicModificationRule $rule, QmInspectionStageLog $log): string
    {
        // Any failure pushes back to normal
        if ($log->consecutive_fail > 0) {
            return QmInspectionStageLog::STAGE_NORMAL;
        }

        if ($log->consecutive_pass >= $rule->skip_after_reduced_pass) {
            return QmInspectionStageLog::STAGE_SKIP;
        }

        return QmInspectionStageLog::STAGE_REDUCED;
    }
}
