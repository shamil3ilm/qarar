<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Maintenance\MaintenanceOrderCostLine;
use App\Models\Maintenance\MaintenanceOrderSettlement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceOrderSettlementService
{
    /**
     * Record a cost line against a maintenance order.
     */
    public function recordCost(array $data): MaintenanceOrderCostLine
    {
        $totalCost = $data['total_cost']
            ?? ((float) ($data['quantity'] ?? 1) * (float) ($data['unit_cost'] ?? 0));

        return MaintenanceOrderCostLine::create([
            'organization_id'      => auth()->user()->organization_id,
            'maintenance_order_id' => $data['maintenance_order_id'],
            'cost_element_id'      => $data['cost_element_id'] ?? null,
            'cost_type'            => $data['cost_type'],
            'quantity'             => $data['quantity'] ?? null,
            'unit_cost'            => $data['unit_cost'] ?? null,
            'total_cost'           => $totalCost,
            'currency_code'        => $data['currency_code'],
            'posting_date'         => $data['posting_date'] ?? now()->toDateString(),
            'vendor_id'            => $data['vendor_id'] ?? null,
            'employee_id'          => $data['employee_id'] ?? null,
        ]);
    }

    /**
     * Get total cost breakdown by cost type for a maintenance order.
     *
     * @return array{by_type: array<string, float>, total: float}
     */
    public function getTotalCost(int $maintenanceOrderId): array
    {
        $lines = MaintenanceOrderCostLine::where('maintenance_order_id', $maintenanceOrderId)
            ->get();

        $byType = [];
        foreach ($lines as $line) {
            $type = $line->cost_type;
            $byType[$type] = ($byType[$type] ?? 0.0) + (float) $line->total_cost;
        }

        $total = array_sum($byType);

        return [
            'by_type' => $byType,
            'total'   => round($total, 4),
        ];
    }

    /**
     * Settle a maintenance order's costs to one or more receivers.
     *
     * Each rule in $rules must contain:
     *  - receiver_type: cost_center|asset|order|wbs
     *  - receiver_id: int
     *  - percentage: float (must sum to 100 for full settlement)
     *
     * @return Collection<int, MaintenanceOrderSettlement>
     */
    public function settle(int $maintenanceOrderId, array $rules): Collection
    {
        return DB::transaction(function () use ($maintenanceOrderId, $rules): Collection {
            $orgId         = auth()->user()->organization_id;
            $userId        = auth()->id();
            $costData      = $this->getTotalCost($maintenanceOrderId);
            $totalCost     = $costData['total'];
            $settlementDate = now()->toDateString();
            $fiscalYear    = (int) now()->format('Y');
            $period        = (int) now()->format('n');
            $settlements   = new Collection();

            foreach ($rules as $rule) {
                $percentage    = (float) ($rule['percentage'] ?? 100);
                $settledAmount = round($totalCost * $percentage / 100, 4);
                $ruleType      = count($rules) === 1 ? MaintenanceOrderSettlement::RULE_FULL
                    : MaintenanceOrderSettlement::RULE_PARTIAL;

                // Create a journal entry stub for audit trail
                $journalEntry = $this->createSettlementJournalEntry(
                    $orgId,
                    $maintenanceOrderId,
                    $settledAmount,
                    $rule,
                    $settlementDate,
                    $userId,
                );

                $settlement = MaintenanceOrderSettlement::create([
                    'organization_id'       => $orgId,
                    'maintenance_order_id'  => $maintenanceOrderId,
                    'settlement_rule_type'  => $ruleType,
                    'receiver_type'         => $rule['receiver_type'],
                    'receiver_id'           => $rule['receiver_id'],
                    'percentage'            => $percentage,
                    'settled_amount'        => $settledAmount,
                    'settlement_date'       => $settlementDate,
                    'fiscal_year'           => $fiscalYear,
                    'period'                => $period,
                    'journal_entry_id'      => $journalEntry?->id,
                    'created_by'            => $userId,
                ]);

                $settlements->push($settlement);
            }

            return $settlements;
        });
    }

    /**
     * Get maintenance orders that have cost lines but no settlements.
     */
    public function getUnsettledOrders(int $organizationId): Collection
    {
        $settledOrderIds = MaintenanceOrderSettlement::where('organization_id', $organizationId)
            ->pluck('maintenance_order_id')
            ->unique();

        return MaintenanceOrderCostLine::where('organization_id', $organizationId)
            ->whereNotIn('maintenance_order_id', $settledOrderIds)
            ->selectRaw('maintenance_order_id, SUM(total_cost) as total_cost, COUNT(*) as line_count')
            ->groupBy('maintenance_order_id')
            ->get();
    }

    /**
     * Get settlement history for a maintenance order.
     */
    public function getSettlementHistory(int $maintenanceOrderId): Collection
    {
        return MaintenanceOrderSettlement::where('maintenance_order_id', $maintenanceOrderId)
            ->with(['journalEntry', 'creator'])
            ->orderBy('settlement_date')
            ->get();
    }

    /**
     * Create a minimal journal entry for cost settlement traceability.
     */
    private function createSettlementJournalEntry(
        int $orgId,
        int $maintenanceOrderId,
        float $settledAmount,
        array $rule,
        string $date,
        ?int $userId,
    ): ?JournalEntry {
        try {
            $entry = JournalEntry::create([
                'organization_id' => $orgId,
                'entry_number'    => 'MOS-' . $maintenanceOrderId . '-' . time(),
                'entry_date'      => $date,
                'description'     => "Maintenance order #{$maintenanceOrderId} cost settlement to "
                    . $rule['receiver_type'] . ' #' . $rule['receiver_id'],
                'status'          => 'posted',
                'created_by'      => $userId,
                'reference_type'  => 'maintenance_order',
                'reference_id'    => $maintenanceOrderId,
            ]);

            return $entry;
        } catch (\Throwable $e) {
            logger()->warning('Failed to create settlement journal entry', [
                'maintenance_order_id' => $maintenanceOrderId,
                'error'                => $e->getMessage(),
            ]);

            return null;
        }
    }
}
