<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\AtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtpController extends Controller
{
    public function __construct(
        private AtpService $atpService
    ) {}

    /**
     * POST /api/v1/sales/atp/check
     * Check availability for a single product/quantity/date.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'     => 'required|integer|exists:products,id',
            'quantity'       => 'required|numeric|min:0.0001',
            'requested_date' => 'required|date',
            'warehouse_id'   => 'nullable|integer|exists:warehouses,id',
        ]);

        $orgId = $this->organizationId($request);

        $result = $this->atpService->checkAvailability(
            (int) $validated['product_id'],
            (float) $validated['quantity'],
            (string) $validated['requested_date'],
            (int) $orgId,
            isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null
        );

        return $this->success($result, 'ATP check completed.');
    }

    /**
     * POST /api/v1/sales/atp/check-order
     * Check availability for all lines of an order.
     */
    public function checkOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'             => 'required|integer|exists:contacts,id',
            'lines'                  => 'required|array|min:1',
            'lines.*.product_id'     => 'required|integer|exists:products,id',
            'lines.*.quantity'       => 'required|numeric|min:0.0001',
            'lines.*.requested_date' => 'required|date',
            'lines.*.warehouse_id'   => 'nullable|integer|exists:warehouses,id',
            'persist'                => 'nullable|boolean',
            'source_document_type'   => 'nullable|string|max:30',
            'source_document_id'     => 'nullable|integer',
        ]);

        $orgId = (int) $this->organizationId($request);

        $result = $this->atpService->checkOrderLines(
            $validated['lines'],
            (int) $validated['contact_id'],
            $orgId
        );

        // Optionally persist the ATP results for audit trail
        if (!empty($validated['persist'])
            && !empty($validated['source_document_type'])
            && !empty($validated['source_document_id'])
        ) {
            $this->atpService->persistAtpResult(
                (string) $validated['source_document_type'],
                (int) $validated['source_document_id'],
                $result
            );
        }

        return $this->success($result, 'Order ATP check completed.');
    }
}
