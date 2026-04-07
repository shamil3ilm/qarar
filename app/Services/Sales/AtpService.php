<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Inventory\StockLevel;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Sales\AtpCheck;
use App\Models\Sales\SalesOrderLine;
use App\Models\Sales\InvoiceLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AtpService
{
    /**
     * Check availability for a single product/quantity/date combination.
     *
     * @param  int         $productId
     * @param  float       $quantity       Requested quantity
     * @param  string      $requestedDate  ISO date string (Y-m-d)
     * @param  int         $orgId
     * @param  int|null    $warehouseId
     * @return array{confirmed_qty:float,confirmed_date:string|null,available_stock:float,incoming_po:float,planned_production:float,committed:float,result:string}
     */
    public function checkAvailability(
        int $productId,
        float $quantity,
        string $requestedDate,
        int $orgId,
        ?int $warehouseId = null
    ): array {
        // 1. Current stock levels
        $stockQuery = StockLevel::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('product_id', $productId);

        if ($warehouseId !== null) {
            $stockQuery->where('warehouse_id', $warehouseId);
        }

        $stockLevels = $stockQuery->get();
        $availableStock = (float) $stockLevels->sum(fn ($s) => max(0, (float) $s->quantity - (float) $s->reserved_quantity));

        // 2. Incoming from open purchase order lines with expected_delivery_date <= requestedDate
        $incomingPo = (float) PurchaseOrderLine::withoutGlobalScope('organization')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.organization_id', $orgId)
            ->where('purchase_order_lines.product_id', $productId)
            ->whereIn('purchase_orders.status', ['confirmed', 'partially_received'])
            ->where('purchase_orders.expected_delivery_date', '<=', $requestedDate)
            ->selectRaw('SUM(purchase_order_lines.quantity - COALESCE(purchase_order_lines.quantity_received, 0)) as total')
            ->value('total') ?? 0.0;

        // 3. Planned production from work orders
        $plannedProduction = 0.0;
        if (class_exists(WorkOrder::class)) {
            $plannedProduction = (float) DB::table('work_orders')
                ->where('organization_id', $orgId)
                ->where('product_id', $productId)
                ->whereIn('status', ['planned', 'in_progress'])
                ->where('planned_end_date', '<=', $requestedDate)
                ->sum(DB::raw('planned_quantity - COALESCE(produced_quantity, 0)'));
        }

        // 4. Already committed quantities (open sales order lines)
        $committed = (float) DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->where('sales_orders.organization_id', $orgId)
            ->where('sales_order_lines.product_id', $productId)
            ->whereNotIn('sales_orders.status', ['cancelled', 'invoiced'])
            ->sum(DB::raw('sales_order_lines.quantity - COALESCE(sales_order_lines.quantity_delivered, 0)'));

        // Net available = stock + incoming_po + production - committed
        $netAvailable = max(0.0, $availableStock + $incomingPo + $plannedProduction - $committed);

        $confirmedQty  = min($quantity, $netAvailable);
        $result        = $this->determineResult($quantity, $confirmedQty);
        $confirmedDate = $confirmedQty > 0 ? $requestedDate : null;

        return [
            'confirmed_qty'      => $confirmedQty,
            'confirmed_date'     => $confirmedDate,
            'available_stock'    => $availableStock,
            'incoming_po'        => $incomingPo,
            'planned_production' => $plannedProduction,
            'committed'          => $committed,
            'result'             => $result,
        ];
    }

    /**
     * Run ATP check for all lines of an order and return the full result.
     *
     * @param  array<int,array{product_id:int,quantity:float,requested_date:string,warehouse_id?:int}>  $lines
     * @param  int  $contactId
     * @param  int  $orgId
     * @return array{lines:array,earliest_delivery_date:string|null,overall_result:string}
     */
    public function checkOrderLines(array $lines, int $contactId, int $orgId): array
    {
        $lineResults     = [];
        $confirmedDates  = [];
        $overallResult   = AtpCheck::RESULT_FULL;

        foreach ($lines as $index => $line) {
            $result = $this->checkAvailability(
                (int) $line['product_id'],
                (float) $line['quantity'],
                (string) $line['requested_date'],
                $orgId,
                isset($line['warehouse_id']) ? (int) $line['warehouse_id'] : null
            );

            $lineResults[$index] = array_merge($line, $result);

            if ($result['confirmed_date'] !== null) {
                $confirmedDates[] = $result['confirmed_date'];
            }

            if ($result['result'] === AtpCheck::RESULT_NONE) {
                $overallResult = AtpCheck::RESULT_NONE;
            } elseif ($result['result'] === AtpCheck::RESULT_PARTIAL && $overallResult === AtpCheck::RESULT_FULL) {
                $overallResult = AtpCheck::RESULT_PARTIAL;
            }
        }

        $earliestDeliveryDate = empty($confirmedDates)
            ? null
            : max($confirmedDates);

        return [
            'lines'                  => $lineResults,
            'earliest_delivery_date' => $earliestDeliveryDate,
            'overall_result'         => $overallResult,
        ];
    }

    /**
     * Persist ATP check results as audit records.
     *
     * @param  string  $docType
     * @param  int     $docId
     * @param  array   $atpResults  Output from checkOrderLines()
     */
    public function persistAtpResult(string $docType, int $docId, array $atpResults): void
    {
        $orgId = auth()->user()?->organization_id;

        foreach ($atpResults['lines'] as $line) {
            try {
                AtpCheck::withoutGlobalScope('organization')->updateOrCreate(
                    [
                        'organization_id'      => $orgId,
                        'source_document_type' => $docType,
                        'source_document_id'   => $docId,
                        'product_id'           => (int) $line['product_id'],
                    ],
                    [
                        'warehouse_id'           => isset($line['warehouse_id']) ? (int) $line['warehouse_id'] : null,
                        'requested_quantity'     => (float) $line['quantity'],
                        'confirmed_quantity'     => (float) $line['confirmed_qty'],
                        'requested_date'         => $line['requested_date'],
                        'confirmed_date'         => $line['confirmed_date'],
                        'availability_breakdown' => [
                            'stock'              => $line['available_stock'],
                            'incoming_po'        => $line['incoming_po'],
                            'production'         => $line['planned_production'],
                            'committed'          => $line['committed'],
                        ],
                        'result' => $line['result'],
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('Failed to persist ATP result', [
                    'doc_type'   => $docType,
                    'doc_id'     => $docId,
                    'product_id' => $line['product_id'],
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    private function determineResult(float $requested, float $confirmed): string
    {
        if ($confirmed <= 0.0) {
            return AtpCheck::RESULT_NONE;
        }

        if (bccomp((string) $confirmed, (string) $requested, 4) >= 0) {
            return AtpCheck::RESULT_FULL;
        }

        return AtpCheck::RESULT_PARTIAL;
    }
}
