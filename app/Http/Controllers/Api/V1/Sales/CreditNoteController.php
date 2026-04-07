<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use App\Services\Sales\CreditNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CreditNoteController extends Controller
{

    public function __construct(
        protected CreditNoteService $creditNoteService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $creditNotes = $this->creditNoteService->list(
            $request->user()->organization_id,
            $request->only(['status', 'contact_id', 'type', 'from_date', 'to_date', 'has_balance']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($creditNotes);
    }

    public function store(Request $request): JsonResponse
    {
        // Accept both 'lines' and 'items' keys
        $data = $request->all();
        if (isset($data['lines']) && !isset($data['items'])) {
            $data['items'] = $data['lines'];
        }

        $validator = Validator::make($data, [
            'credit_note_type' => 'required|in:sales,purchase',
            'contact_id' => 'required|exists:contacts,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'credit_note_date' => 'required|date',
            'currency_code' => 'required|string|size:3',
            'reason' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            // Validate that total doesn't exceed invoice amount
            if (!empty($data['invoice_id'])) {
                $invoice = Invoice::find($data['invoice_id']);
                if ($invoice) {
                    $total = 0;
                    foreach ($data['items'] as $item) {
                        $subtotal = (float) bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
                        $taxAmount = (float) bcmul((string) $subtotal, bcdiv((string) ($item['tax_rate'] ?? 0), '100', 4), 2);
                        $total = (float) bcadd((string) $total, (string) bcadd((string) $subtotal, (string) $taxAmount, 2), 2);
                    }
                    if ($total > (float) $invoice->total) {
                        return $this->error('Credit note total exceeds invoice total.', 'VALIDATION_ERROR', 422);
                    }
                }
            }

            $creditNote = $this->creditNoteService->create(
                array_merge($data, ['organization_id' => $request->user()->organization_id]),
                $request->user()->id
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($creditNote, 'Credit note created successfully.');
    }

    public function show(CreditNote $creditNote): JsonResponse
    {
        return $this->success($creditNote->load(['items.product', 'contact', 'invoice', 'applications.invoice']));
    }

    public function approve(Request $request, CreditNote $creditNote): JsonResponse
    {
        try {
            $creditNote = $this->creditNoteService->approve($creditNote, $request->user()->id);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($creditNote, 'Credit note approved.');
    }

    public function apply(Request $request, CreditNote $creditNote): JsonResponse
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        try {
            $application = $this->creditNoteService->applyToInvoice($creditNote, $invoice, (float) $request->amount);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\App\Exceptions\ERP\ErpException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getHttpStatus());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($application, 'Credit note applied to invoice.');
    }

    public function void(CreditNote $creditNote): JsonResponse
    {
        try {
            $creditNote = $this->creditNoteService->void($creditNote);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($creditNote, 'Credit note voided.');
    }
}
