<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\AdvancePayment;
use App\Models\Sales\Invoice;
use App\Services\Sales\CustomerAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAdvanceController extends Controller
{
    public function __construct(
        private CustomerAdvanceService $advanceService,
    ) {}

    /**
     * GET /sales/customer-advances
     */
    public function index(Request $request): JsonResponse
    {
        $filters = array_merge(
            $request->only(['contact_id', 'status', 'from_date', 'to_date', 'per_page']),
            ['organization_id' => $request->user()->organization_id],
        );

        return $this->paginated($this->advanceService->index($filters));
    }

    /**
     * POST /sales/customer-advances
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'     => ['required', 'exists:contacts,id'],
            'advance_date'   => ['required', 'date'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'currency_code'  => ['required', 'string', 'size:3'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference'      => ['nullable', 'string', 'max:100'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $advance = $this->advanceService->store(array_merge($validated, [
            'organization_id' => $request->user()->organization_id,
            'created_by'      => $request->user()->id,
        ]));

        return $this->created($advance->load(['contact:id,contact_name,company_name', 'applications']));
    }

    /**
     * GET /sales/customer-advances/{advancePayment}
     */
    public function show(AdvancePayment $advancePayment): JsonResponse
    {
        return $this->success(
            $advancePayment->load(['contact:id,contact_name,company_name', 'applications.invoice:id,invoice_number'])
        );
    }

    /**
     * DELETE /sales/customer-advances/{advancePayment}
     */
    public function destroy(AdvancePayment $advancePayment): JsonResponse
    {
        if ($advancePayment->status !== AdvancePayment::STATUS_DRAFT) {
            return $this->error('Only draft advances can be deleted.', 'INVALID_STATUS', 422);
        }

        $advancePayment->delete();

        return $this->success(null, 'Advance payment deleted.');
    }

    /**
     * POST /sales/customer-advances/{advancePayment}/apply
     */
    public function applyToInvoice(Request $request, AdvancePayment $advancePayment): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id'    => ['required', 'exists:invoices,id'],
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $invoice     = Invoice::findOrFail($validated['invoice_id']);
        $application = $this->advanceService->applyToInvoice(
            $advancePayment,
            $invoice,
            (float) $validated['amount'],
            $validated['notes'] ?? null,
        );

        return $this->created($application->load(['advancePayment', 'invoice:id,invoice_number,amount_due']));
    }

    /**
     * GET /sales/customer-advances/contact/{contactId}/open
     */
    public function openAdvances(Request $request, int $contactId): JsonResponse
    {
        $advances = $this->advanceService->getOpenAdvancesForContact(
            $contactId,
            $request->user()->organization_id,
        );

        return $this->success($advances);
    }

    /**
     * POST /sales/customer-advances/{advancePayment}/refund
     */
    public function refund(AdvancePayment $advancePayment): JsonResponse
    {
        $this->advanceService->refund($advancePayment);

        return $this->success($advancePayment->fresh(), 'Advance refunded successfully.');
    }
}
