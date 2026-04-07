<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\ThreeWayMatchResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreeWayMatchController extends Controller
{
    /**
     * GET /api/v1/purchase/three-way-match
     * Returns all three-way match results for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = (int) $request->user()->organization_id;

        $results = ThreeWayMatchResult::with(['bill', 'purchaseOrderLine', 'goodsReceiptLine'])
            ->whereHas('bill', fn ($q) => $q->where('organization_id', $orgId))
            ->when(
                $request->match_status,
                fn ($q, $status) => $q->where('match_status', $status)
            )
            ->when(
                $request->bill_id,
                fn ($q, $billId) => $q->where('bill_id', $billId)
            )
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return $this->paginated($results);
    }

    /**
     * GET /api/v1/purchase/three-way-match/exceptions
     * Returns only lines with matching exceptions (unmatched).
     */
    public function exceptions(Request $request): JsonResponse
    {
        $orgId = (int) $request->user()->organization_id;

        $exceptions = ThreeWayMatchResult::with(['bill', 'purchaseOrderLine', 'goodsReceiptLine'])
            ->whereHas('bill', fn ($q) => $q->where('organization_id', $orgId))
            ->unmatched()
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return $this->paginated($exceptions);
    }
}
