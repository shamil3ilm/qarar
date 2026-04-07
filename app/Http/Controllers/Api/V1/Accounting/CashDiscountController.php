<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\CashDiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashDiscountController extends Controller
{
    public function __construct(private readonly CashDiscountService $service) {}

    /**
     * List all active payment terms for the organization.
     *
     * GET /accounting/payment-terms
     */
    public function indexTerms(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->getPaymentTerms($request->user()->organization_id)
        );
    }

    /**
     * Create a new payment term.
     *
     * POST /accounting/payment-terms
     */
    public function storeTerms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'          => 'required|string|max:20',
            'name'          => 'required|string|max:100',
            'net_days'      => 'required|integer|min:0|max:365',
            'discount_days' => 'required|integer|min:0|max:365',
            'discount_pct'  => 'required|numeric|min:0|max:100',
        ]);

        $term = $this->service->createPaymentTerm($request->user()->organization_id, $validated);

        return $this->created($term);
    }

    /**
     * Preview the cash discount for an invoice without posting.
     *
     * POST /accounting/cash-discounts/preview
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id'      => 'required|integer',
            'payment_term_id' => 'required|integer',
            'payment_date'    => 'required|date',
        ]);

        $result = $this->service->previewArDiscount(
            $request->user()->organization_id,
            $validated['invoice_id'],
            $validated['payment_term_id'],
            $validated['payment_date']
        );

        return $this->success($result);
    }

    /**
     * Apply a cash discount to an AR invoice and post the GL entry.
     *
     * POST /accounting/cash-discounts/apply
     */
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id'             => 'required|integer',
            'payment_term_id'        => 'required|integer',
            'payment_date'           => 'required|date',
            'discount_gl_account_id' => 'required|integer',
        ]);

        $result = $this->service->applyArDiscount(
            $request->user()->organization_id,
            $validated['invoice_id'],
            $validated['payment_term_id'],
            $validated['payment_date'],
            $validated['discount_gl_account_id']
        );

        return $this->success($result, 'Cash discount applied');
    }
}
