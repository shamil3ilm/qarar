<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use App\Services\Inventory\MaterialValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialValuationController extends Controller
{
    public function __construct(
        private readonly MaterialValuationService $service
    ) {}

    /**
     * GET /inventory/valuation/inventory-value?warehouse_id=
     */
    public function inventoryValue(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $orgId       = $this->organizationId($request);
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $data = $this->service->calculateInventoryValue($orgId, $warehouseId);

        return $this->success($data, 'Inventory value calculated.');
    }

    /**
     * POST /inventory/valuation/revalue
     * Body: { product_id, new_unit_cost }
     */
    public function revalue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'    => ['required', 'integer', 'min:1'],
            'new_unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $orgId = $this->organizationId($request);

        $this->service->revalueInventory(
            orgId:       $orgId,
            productId:   (int) $validated['product_id'],
            newUnitCost: (float) $validated['new_unit_cost']
        );

        return $this->success(null, 'Inventory revaluation posted successfully.');
    }

    /**
     * GET /inventory/valuation/variance-report?from=&to=
     */
    public function varianceReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $orgId = $this->organizationId($request);
        $from  = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to    = $validated['to']   ?? now()->toDateString();

        $perPage = $request->integer('per_page', 25);

        // Pull journal entries that represent variance postings (PPV / REVAL)
        $paginator = JournalEntry::where('organization_id', $orgId)
            ->where(function ($q) {
                $q->where('reference', 'like', 'PPV-%')
                    ->orWhere('reference', 'like', 'REVAL-%');
            })
            ->whereBetween('entry_date', [$from, $to])
            ->with('lines.account')
            ->orderByDesc('entry_date')
            ->paginate($perPage);

        $entries = collect($paginator->items())->map(fn (JournalEntry $je) => [
            'id'          => $je->id,
            'reference'   => $je->reference,
            'type'        => str_starts_with($je->reference ?? '', 'PPV') ? 'price_variance' : 'revaluation',
            'date'        => $je->entry_date,
            'description' => $je->description,
            'amount'      => $je->lines->where('debit', '>', 0)->sum('debit'),
        ]);

        return $this->success([
            'from'    => $from,
            'to'      => $to,
            'entries' => $entries,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], 'Variance report generated.');
    }
}
