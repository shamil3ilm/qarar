<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionResourceTool;
use App\Models\Manufacturing\PrtOperationAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionResourceToolService
{
    public function list(array $filters = []): Collection
    {
        return ProductionResourceTool::query()
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['prt_type']), fn($q) => $q->forType($filters['prt_type']))
            ->when(isset($filters['search']), function ($q) use ($filters): void {
                $q->where(function ($query) use ($filters): void {
                    $query->where('prt_number', 'like', "%{$filters['search']}%")
                        ->orWhere('prt_name', 'like', "%{$filters['search']}%");
                });
            })
            ->orderBy('prt_number')
            ->get();
    }

    public function create(array $data): ProductionResourceTool
    {
        return ProductionResourceTool::create($data);
    }

    public function update(ProductionResourceTool $prt, array $data): ProductionResourceTool
    {
        $prt->update($data);

        return $prt->fresh();
    }

    public function assign(ProductionResourceTool $prt, array $data): PrtOperationAssignment
    {
        return DB::transaction(function () use ($prt, $data): PrtOperationAssignment {
            $quantityRequired = (int) ($data['quantity_required'] ?? 1);

            if (!$prt->isAvailable()) {
                throw ValidationException::withMessages([
                    'prt' => 'This tool/resource is not currently available for assignment.',
                ]);
            }

            $availableUnits = $prt->quantity_available - $prt->quantity_in_use;

            if ($availableUnits < $quantityRequired) {
                throw ValidationException::withMessages([
                    'quantity_required' => "Only {$availableUnits} unit(s) available, but {$quantityRequired} requested.",
                ]);
            }

            $assignment = PrtOperationAssignment::create([
                'organization_id' => $prt->organization_id,
                'production_resource_tool_id' => $prt->id,
                'assigned_at' => now(),
                'status' => 'assigned',
                ...$data,
            ]);

            $prt->assignTo($data['work_order_id'] ?? 0, $quantityRequired);

            return $assignment;
        });
    }

    public function release(PrtOperationAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $prt = $assignment->productionResourceTool;

            $assignment->update([
                'status' => 'released',
                'released_at' => now(),
            ]);

            $prt->release($assignment->quantity_required);
        });
    }

    public function getForWorkOrder(int $workOrderId): Collection
    {
        return PrtOperationAssignment::with('productionResourceTool')
            ->where('work_order_id', $workOrderId)
            ->get();
    }

    public function getAvailable(?string $type = null): Collection
    {
        return ProductionResourceTool::available()
            ->when($type !== null, fn($q) => $q->forType($type))
            ->get();
    }
}
