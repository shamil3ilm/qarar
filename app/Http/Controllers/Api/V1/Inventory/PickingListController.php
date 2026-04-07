<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PickingListController extends Controller
{
    /**
     * List picking lists filtered by warehouse, status, or source document.
     * SAP equivalent: LT0A (Transfer Order list / Picking list overview).
     *
     * Note: Picking lists in this system are managed via Wave plans (WaveController).
     * This controller provides a standalone view filtered per warehouse / sales order.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $query = DB::table('picking_lists')
            ->where('organization_id', $orgId)
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->sales_order_id, fn ($q, $id) => $q->where('sales_order_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('picking_number', 'like', "%{$s}%"))
            ->orderByDesc('created_at');

        $perPage = $request->integer('per_page', 15);
        $results = $query->paginate($perPage);

        return $this->paginatedRaw($results);
    }

    /**
     * Create a picking list from a sales order or stock transfer order.
     * SAP equivalent: LT01 (Create Transfer Order / Picking list).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'      => ['required', 'integer', 'exists:warehouses,id'],
            'source_type'       => ['required', 'string', 'in:sales_order,stock_transfer'],
            'source_id'         => ['required', 'integer'],
            'picking_date'      => ['required', 'date'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        $orgId = $request->user()->organization_id;

        $pickingNumber = 'PICK-' . strtoupper(uniqid());

        $id = DB::table('picking_lists')->insertGetId([
            'organization_id' => $orgId,
            'warehouse_id'    => $validated['warehouse_id'],
            'source_type'     => $validated['source_type'],
            'source_id'       => $validated['source_id'],
            'picking_number'  => $pickingNumber,
            'picking_date'    => $validated['picking_date'],
            'status'          => 'open',
            'notes'           => $validated['notes'] ?? null,
            'created_by'      => $request->user()->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $record = DB::table('picking_lists')->find($id);

        return $this->created($record);
    }

    /**
     * Get a picking list with its lines.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $list = DB::table('picking_lists')
            ->where('organization_id', $orgId)
            ->where('uuid', $uuid)
            ->first();

        if (! $list) {
            return $this->pickingListNotFound('Picking list not found.');
        }

        $lines = DB::table('picking_list_lines')
            ->where('picking_list_id', $list->id)
            ->get();

        return $this->success([
            'picking_list' => $list,
            'lines'        => $lines,
        ]);
    }

    /**
     * Confirm items picked — updates stock levels and marks lines as picked.
     * SAP equivalent: LT1A (Confirm Transfer Order / Picking confirmation).
     */
    public function confirmPick(Request $request, string $uuid): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $list = DB::table('picking_lists')
            ->where('organization_id', $orgId)
            ->where('uuid', $uuid)
            ->first();

        if (! $list) {
            return $this->pickingListNotFound('Picking list not found.');
        }

        if ($list->status === 'completed') {
            return $this->error('Picking list is already completed.', 422);
        }

        $validated = $request->validate([
            'lines'                   => ['required', 'array', 'min:1'],
            'lines.*.line_id'         => ['required', 'integer'],
            'lines.*.picked_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($list, $validated, $request) {
            foreach ($validated['lines'] as $lineInput) {
                $line = DB::table('picking_list_lines')
                    ->where('id', $lineInput['line_id'])
                    ->where('picking_list_id', $list->id)
                    ->first();

                if (! $line) {
                    continue;
                }

                DB::table('picking_list_lines')
                    ->where('id', $line->id)
                    ->update([
                        'picked_quantity' => $lineInput['picked_quantity'],
                        'status'          => 'picked',
                        'picked_by'       => $request->user()->id,
                        'picked_at'       => now(),
                        'updated_at'      => now(),
                    ]);

                // Reduce available stock at the source location
                if (isset($line->product_id) && isset($line->warehouse_id)) {
                    StockLevel::where('product_id', $line->product_id)
                        ->where('warehouse_id', $line->warehouse_id)
                        ->decrement('quantity', $lineInput['picked_quantity']);
                }
            }

            // Mark list as completed if all lines are picked
            $unpickedCount = DB::table('picking_list_lines')
                ->where('picking_list_id', $list->id)
                ->where('status', '!=', 'picked')
                ->count();

            $newStatus = $unpickedCount === 0 ? 'completed' : 'in_progress';

            DB::table('picking_lists')
                ->where('id', $list->id)
                ->update(['status' => $newStatus, 'updated_at' => now()]);
        });

        $updated = DB::table('picking_lists')->find($list->id);

        return $this->success($updated, 'Pick confirmed successfully.');
    }

    // ------------------------------------------------------------------
    // Internal helpers for raw paginator (no Eloquent model available)
    // ------------------------------------------------------------------

    private function paginatedRaw(mixed $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links'   => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    private function pickingListNotFound(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => ['code' => 'NOT_FOUND', 'message' => $message],
        ], 404);
    }
}
