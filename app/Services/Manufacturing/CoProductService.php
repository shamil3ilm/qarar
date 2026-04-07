<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\BomCoProduct;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrderCoProductActual;
use Illuminate\Support\Collection;

class CoProductService
{
    public function getForBom(int $bomId): Collection
    {
        return BomCoProduct::with('product')
            ->forBom($bomId)
            ->orderBy('co_product_type')
            ->get();
    }

    public function addCoProduct(BomTemplate $bom, array $data): BomCoProduct
    {
        return BomCoProduct::create([
            'organization_id' => $bom->organization_id,
            'bom_template_id' => $bom->id,
            ...$data,
        ]);
    }

    public function updateCoProduct(BomCoProduct $coProduct, array $data): BomCoProduct
    {
        $coProduct->update($data);

        return $coProduct->fresh();
    }

    public function removeCoProduct(BomCoProduct $coProduct): void
    {
        $coProduct->delete();
    }

    public function getForWorkOrder(int $workOrderId): Collection
    {
        return WorkOrderCoProductActual::with(['product', 'warehouse', 'bomCoProduct'])
            ->where('work_order_id', $workOrderId)
            ->get();
    }

    /**
     * Create or update WorkOrderCoProductActual records for a work order.
     *
     * @param  array<int, array{product_id: int, co_product_type?: string, actual_quantity: float, planned_quantity?: float, unit_of_measure?: string, warehouse_id?: int, bom_co_product_id?: int}>  $actuals
     * @return array<int, WorkOrderCoProductActual>
     */
    public function postActual(int $workOrderId, array $actuals): array
    {
        $results = [];

        foreach ($actuals as $actualData) {
            $existing = WorkOrderCoProductActual::where('work_order_id', $workOrderId)
                ->where('product_id', $actualData['product_id'])
                ->first();

            if ($existing !== null) {
                $existing->update($actualData);
                $results[] = $existing->fresh();
            } else {
                $results[] = WorkOrderCoProductActual::create([
                    'work_order_id' => $workOrderId,
                    ...$actualData,
                ]);
            }
        }

        return $results;
    }

    public function postToStock(WorkOrderCoProductActual $actual): void
    {
        $actual->update(['posted_to_stock' => true]);
    }
}
