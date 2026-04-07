<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorCreditNote;
use App\Services\Purchase\VendorCreditNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorCreditNoteController extends Controller
{
    public function __construct(
        private VendorCreditNoteService $service
    ) {}

    /**
     * List vendor credit notes.
     */
    public function index(Request $request): JsonResponse
    {
        $notes = $this->service->list($request->only([
            'vendor_id', 'status', 'start_date', 'end_date', 'per_page',
        ]));

        return $this->paginated($notes);
    }

    /**
     * Create a vendor credit note.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id' => [
                'required',
                Rule::exists('contacts', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'bill_id' => [
                'nullable',
                Rule::exists('bills', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'credit_note_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['required', 'date'],
            'credit_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $headerData = collect($validated)->except('lines')->toArray();
        $headerData['organization_id'] = auth()->user()->organization_id;

        try {
            $creditNote = $this->service->create($headerData, $validated['lines']);
            return $this->created($creditNote, 'Vendor credit note created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a vendor credit note.
     */
    public function show(VendorCreditNote $vendorCreditNote): JsonResponse
    {
        $vendorCreditNote->load(['vendor', 'bill', 'lines.product', 'postedBy', 'voidedBy']);

        return $this->success($vendorCreditNote);
    }

    /**
     * Update a draft vendor credit note.
     */
    public function update(Request $request, VendorCreditNote $vendorCreditNote): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id' => [
                'sometimes',
                Rule::exists('contacts', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'bill_id' => [
                'nullable',
                Rule::exists('bills', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'issue_date' => ['sometimes', 'date'],
            'credit_date' => ['sometimes', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $creditNote = $this->service->update(
                $vendorCreditNote,
                collect($validated)->except('lines')->toArray(),
                $validated['lines'] ?? null
            );
            return $this->success($creditNote, 'Vendor credit note updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Delete a draft vendor credit note.
     */
    public function destroy(VendorCreditNote $vendorCreditNote): JsonResponse
    {
        try {
            $this->service->delete($vendorCreditNote);
            return $this->success(null, 'Vendor credit note deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Post a vendor credit note.
     */
    public function post(VendorCreditNote $vendorCreditNote): JsonResponse
    {
        try {
            $creditNote = $this->service->post($vendorCreditNote);
            return $this->success($creditNote, 'Vendor credit note posted successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'POST_FAILED', 422);
        }
    }

    /**
     * Apply a credit note to a bill.
     */
    public function apply(Request $request, VendorCreditNote $vendorCreditNote): JsonResponse
    {
        $validated = $request->validate([
            'bill_id' => [
                'required',
                Rule::exists('bills', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'amount' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $bill = Bill::findOrFail($validated['bill_id']);

        try {
            $this->service->apply($vendorCreditNote, $bill, (float) $validated['amount']);
            return $this->success([
                'credit_note' => $vendorCreditNote->fresh(),
                'bill' => $bill->fresh(),
            ], 'Credit note applied successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'APPLY_FAILED', 422);
        }
    }

    /**
     * Void a vendor credit note.
     */
    public function void(VendorCreditNote $vendorCreditNote): JsonResponse
    {
        try {
            $creditNote = $this->service->void($vendorCreditNote);
            return $this->success($creditNote, 'Vendor credit note voided successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VOID_FAILED', 422);
        }
    }
}
