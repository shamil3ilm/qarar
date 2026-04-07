<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use Illuminate\Support\Carbon;

class CrmReportService
{
    /**
     * Pipeline report: open opportunities grouped by stage with values.
     *
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getPipelineReport(int $organizationId, array $filters = []): array
    {
        $query = Opportunity::query()
            ->with('pipelineStage:id,name,probability')
            ->where('organization_id', $organizationId)
            ->where('status', Opportunity::STATUS_OPEN);

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('expected_close_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('expected_close_date', '<=', $filters['to_date']);
        }

        $opportunities = $query->get();

        $byStage = $opportunities->groupBy('pipeline_stage_id')
            ->map(function ($group, $stageId) {
                /** @var \App\Models\CRM\Opportunity $first */
                $first = $group->first();
                $stageProbability = (int) ($first->pipelineStage?->probability ?? $first->probability ?? 0);
                $stageName        = $first->pipelineStage?->name ?? 'Unknown';

                $weightedValue = $group->sum(function ($opp) use ($stageProbability): string {
                    return bcmul(
                        (string) $opp->amount,
                        bcdiv((string) $stageProbability, '100', 4),
                        4
                    );
                });

                return [
                    'stage_id'       => $stageId,
                    'stage_name'     => $stageName,
                    'probability'    => $stageProbability,
                    'count'          => $group->count(),
                    'total_value'    => $group->sum('amount'),
                    'weighted_value' => $weightedValue,
                ];
            })
            ->values();

        $totalWeighted = $opportunities->sum(function ($opp): string {
            $prob = (int) ($opp->pipelineStage?->probability ?? $opp->probability ?? 0);
            return bcmul(
                (string) $opp->amount,
                bcdiv((string) $prob, '100', 4),
                4
            );
        });

        return [
            'total_opportunities'  => $opportunities->count(),
            'total_pipeline_value' => $opportunities->sum('amount'),
            'weighted_forecast'    => $totalWeighted,
            'by_stage'             => $byStage,
        ];
    }

    /**
     * Win/loss analysis: won vs lost with reasons over a date range.
     *
     * @return array<string, mixed>
     */
    public function getWinLossAnalysis(int $organizationId, string $fromDate, string $toDate): array
    {
        $closed = Opportunity::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [Opportunity::STATUS_WON, Opportunity::STATUS_LOST])
            ->whereBetween('actual_close_date', [$fromDate, $toDate])
            ->get(['status', 'amount', 'lost_reason', 'assigned_to']);

        $won  = $closed->where('status', Opportunity::STATUS_WON);
        $lost = $closed->where('status', Opportunity::STATUS_LOST);

        $lostReasons = $lost->groupBy('lost_reason')
            ->map(fn ($group, $reason) => [
                'reason' => $reason ?: 'Not specified',
                'count'  => $group->count(),
            ])
            ->values();

        $winRate = $closed->count() > 0
            ? round($won->count() / $closed->count() * 100, 1)
            : 0.0;

        return [
            'period'       => ['from' => $fromDate, 'to' => $toDate],
            'won_count'    => $won->count(),
            'lost_count'   => $lost->count(),
            'win_rate_pct' => $winRate,
            'won_value'    => $won->sum('amount'),
            'lost_value'   => $lost->sum('amount'),
            'lost_reasons' => $lostReasons,
        ];
    }

    /**
     * Activity analytics: completed/overdue activities by type over a date range.
     *
     * @return array<string, mixed>
     */
    public function getActivityAnalytics(int $organizationId, string $fromDate, string $toDate): array
    {
        $activities = Activity::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get(['activity_type', 'status', 'assigned_to', 'start_datetime', 'end_datetime', 'completed_at']);

        $byType = $activities->groupBy('activity_type')
            ->map(fn ($group, $type) => [
                'type'      => $type,
                'total'     => $group->count(),
                'completed' => $group->where('status', Activity::STATUS_COMPLETED)->count(),
                'overdue'   => $group->filter(fn ($a) => $a->isOverdue())->count(),
            ])
            ->values();

        $completedCount = $activities->where('status', Activity::STATUS_COMPLETED)->count();
        $completionRate = $activities->count() > 0
            ? round($completedCount / $activities->count() * 100, 1)
            : 0.0;

        return [
            'period'          => ['from' => $fromDate, 'to' => $toDate],
            'total'           => $activities->count(),
            'completed'       => $completedCount,
            'completion_rate' => $completionRate,
            'by_type'         => $byType,
        ];
    }

    /**
     * Lead funnel: conversion rates at each status stage over a date range.
     *
     * @return array<string, mixed>
     */
    public function getLeadFunnel(int $organizationId, string $fromDate, string $toDate): array
    {
        $leads = Lead::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get(['status', 'estimated_value']);

        $stages = [
            Lead::STATUS_NEW,
            Lead::STATUS_CONTACTED,
            Lead::STATUS_QUALIFIED,
            Lead::STATUS_CONVERTED,
            Lead::STATUS_LOST,
            Lead::STATUS_UNQUALIFIED,
        ];

        $total = $leads->count();

        $funnel = array_map(fn (string $stage) => [
            'stage'        => $stage,
            'count'        => $leads->where('status', $stage)->count(),
            'value'        => $leads->where('status', $stage)->sum('estimated_value'),
            'pct_of_total' => $total > 0
                ? round($leads->where('status', $stage)->count() / $total * 100, 1)
                : 0.0,
        ], $stages);

        $convertedCount = $leads->where('status', Lead::STATUS_CONVERTED)->count();

        return [
            'period'              => ['from' => $fromDate, 'to' => $toDate],
            'total_leads'         => $total,
            'conversion_rate_pct' => $total > 0
                ? round($convertedCount / $total * 100, 1)
                : 0.0,
            'funnel'              => $funnel,
        ];
    }
}
