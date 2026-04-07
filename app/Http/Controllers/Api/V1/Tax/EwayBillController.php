<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tax;

use App\Http\Controllers\Controller;
use App\Models\Sales\Invoice;
use App\Models\Tax\EwayBillRecord;
use App\Services\Tax\EwayBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages e-way bills against the canonical eway_bills table introduced
 * in migration 2026_03_25_000002.
 *
 * This controller is distinct from GstController::generateEwayBill /
 * cancelEwayBill, which operate on the older ewaybills table.
 */
class EwayBillController extends Controller
{
    public function __construct(
        private readonly EwayBillService $ewayBillService
    ) {}

    /**
     * List e-way bills for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EwayBillRecord::where('organization_id', auth()->user()->organization_id)
            ->with('invoice')
            ->orderByDesc('generated_at')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('supply_type'), fn($q) => $q->where('supply_type', $request->input('supply_type')));

        $bills = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($bills);
    }

    /**
     * Generate a new e-way bill linked to an invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id'       => ['required', 'integer', 'exists:invoices,id'],
            'eway_bill_number' => ['nullable', 'string', 'max:20'],
            'transporter_name' => ['nullable', 'string', 'max:255'],
            'transporter_id'   => ['nullable', 'string', 'max:50'],
            'vehicle_number'   => ['nullable', 'string', 'max:20'],
            'transport_mode'   => ['nullable', 'in:road,rail,air,ship'],
            'distance_km'      => ['nullable', 'integer', 'min:1'],
            'supply_type'      => ['nullable', 'in:outward,inward'],
            'sub_supply_type'  => ['nullable', 'string', 'max:50'],
            'from_pincode'     => ['nullable', 'string', 'max:10'],
            'to_pincode'       => ['nullable', 'string', 'max:10'],
            'valid_upto'       => ['nullable', 'date'],
        ]);

        $invoice = Invoice::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($validated['invoice_id']);

        $bill = $this->ewayBillService->generate($invoice, $validated);

        return $this->success($bill->load('invoice'), 'E-way bill generated', 201);
    }

    /**
     * Show a single e-way bill.
     */
    public function show(int $id): JsonResponse
    {
        $bill = EwayBillRecord::where('organization_id', auth()->user()->organization_id)
            ->with('invoice')
            ->findOrFail($id);

        return $this->success($bill);
    }

    /**
     * Cancel an active e-way bill.
     */
    public function cancel(int $id): JsonResponse
    {
        $bill = EwayBillRecord::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);

        if ($bill->isCancelled()) {
            return $this->error('E-way bill is already cancelled.', 'ALREADY_CANCELLED', 422);
        }

        $bill = $this->ewayBillService->cancel($bill);

        return $this->success($bill, 'E-way bill cancelled');
    }
}
