<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\BillingPlanItem;
use App\Services\Sales\BillingDueListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * VF04 — Billing Due List controller.
 */
class BillingDueListController extends Controller
{
    public function __construct(private readonly BillingDueListService $service) {}

    /**
     * GET /billing-due-list
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'billing_date_from',
            'billing_date_to',
            'sales_order_id',
            'customer_id',
            'plan_type',
        ]);

        $items = $this->service->getDueList(
            organizationId: $request->user()->organization_id,
            filters:        $filters,
            perPage:        (int) $request->get('per_page', 25),
        );

        return $this->paginatedResponse($items, 'Billing due list retrieved');
    }

    /**
     * POST /billing-due-list/{item}/bill
     * Bill a single due item.
     */
    public function billItem(Request $request, BillingPlanItem $item): JsonResponse
    {
        $invoice = $this->service->billItem($item, $request->user()->id);

        return $this->successResponse($invoice, 'Item billed — draft invoice created', 201);
    }

    /**
     * POST /billing-due-list/collective-run
     * Collective billing run (VF04 "collective billing").
     */
    public function collectiveRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_ids'   => ['array'],
            'item_ids.*' => ['integer'],
        ]);

        $invoices = $this->service->collectiveBillingRun(
            organizationId: $request->user()->organization_id,
            itemIds:        $data['item_ids'] ?? [],
            createdByUserId: $request->user()->id,
        );

        return $this->successResponse(
            ['invoices_created' => count($invoices), 'invoices' => $invoices],
            'Collective billing run completed',
            201,
        );
    }
}
