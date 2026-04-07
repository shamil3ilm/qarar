<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\OpenItemClearingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenItemClearingController extends Controller
{
    public function __construct(private readonly OpenItemClearingService $service) {}

    /**
     * List open AR items for a customer.
     *
     * GET /accounting/open-items/ar?customer_id=123
     */
    public function arOpenItems(Request $request): JsonResponse
    {
        $request->validate(['customer_id' => 'required|integer']);
        $customerId = $request->integer('customer_id');

        return $this->success(
            $this->service->getArOpenItems($request->user()->organization_id, $customerId)
        );
    }

    /**
     * Clear AR open items by applying a payment (FIFO by due date).
     *
     * POST /accounting/open-items/ar/clear
     */
    public function clearAr(Request $request): JsonResponse
    {
        $validated = $request->validate(['payment_id' => 'required|integer']);
        $result    = $this->service->clearArItems(
            $request->user()->organization_id,
            $validated['payment_id']
        );

        return $this->success($result, 'AR items cleared');
    }

    /**
     * List open AP items for a vendor.
     *
     * GET /accounting/open-items/ap?supplier_id=456
     */
    public function apOpenItems(Request $request): JsonResponse
    {
        $request->validate(['supplier_id' => 'required|integer']);
        $supplierId = $request->integer('supplier_id');

        return $this->success(
            $this->service->getApOpenItems($request->user()->organization_id, $supplierId)
        );
    }

    /**
     * Clear AP open items by applying a payment (FIFO by due date).
     *
     * POST /accounting/open-items/ap/clear
     */
    public function clearAp(Request $request): JsonResponse
    {
        $validated = $request->validate(['payment_id' => 'required|integer']);
        $result    = $this->service->clearApItems(
            $request->user()->organization_id,
            $validated['payment_id']
        );

        return $this->success($result, 'AP items cleared');
    }
}
