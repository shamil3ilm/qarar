<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\VendorConsignmentSettlement;
use App\Models\Purchase\VendorConsignmentStock;
use App\Services\Purchase\VendorConsignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorConsignmentController extends Controller
{
    public function __construct(
        private VendorConsignmentService $service,
    ) {}

    /**
     * List all vendor consignment stock records for the organization.
     */
    public function stockIndex(Request $request): JsonResponse
    {
        $query = VendorConsignmentStock::with(['vendor', 'product', 'warehouse', 'unit'])
            ->when($request->vendor_id, fn($q, $id) => $q->forVendor((int) $id))
            ->when($request->product_id, fn($q, $id) => $q->forProduct((int) $id))
            ->when($request->warehouse_id, fn($q, $id) => $q->where('warehouse_id', (int) $id))
            ->when($request->active_only, fn($q) => $q->active())
            ->orderByDesc('last_movement_at');

        $stocks = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($stocks);
    }

    /**
     * Show a single consignment stock record with its receipts and withdrawals.
     */
    public function stockShow(int $id): JsonResponse
    {
        $stock = VendorConsignmentStock::with([
            'vendor', 'product', 'warehouse', 'unit', 'receipts', 'withdrawals',
        ])->findOrFail($id);

        return $this->success($stock);
    }

    /**
     * Record a vendor consignment receipt.
     */
    public function receive(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $validated = $request->validate([
            'vendor_id'             => [
                'required',
                Rule::exists('contacts', 'id')->where('organization_id', $organizationId),
            ],
            'product_id'            => [
                'required',
                Rule::exists('products', 'id')->where('organization_id', $organizationId),
            ],
            'warehouse_id'          => [
                'required',
                Rule::exists('warehouses', 'id')->where('organization_id', $organizationId),
            ],
            'warehouse_location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'purchase_order_id'     => ['nullable', 'exists:purchase_orders,id'],
            'receipt_date'          => ['required', 'date'],
            'quantity_received'     => ['required', 'numeric', 'gt:0'],
            'vendor_price'          => ['required', 'numeric', 'min:0'],
            'currency_code'         => ['required', 'string', 'size:3'],
            'unit_id'               => ['nullable', 'exists:units_of_measure,id'],
            'vendor_delivery_note'  => ['nullable', 'string', 'max:100'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
        ]);

        $receipt = $this->service->receiveConsignmentStock($validated);

        return $this->created($receipt);
    }

    /**
     * Record a withdrawal of vendor consignment stock.
     */
    public function withdraw(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_consignment_stock_id' => ['required', 'exists:vendor_consignment_stocks,id'],
            'withdrawal_date'             => ['required', 'date'],
            'quantity_withdrawn'          => ['required', 'numeric', 'gt:0'],
            'withdrawal_type'             => [
                'required',
                Rule::in(['production', 'sales', 'transfer', 'scrapping']),
            ],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id'   => ['nullable', 'integer'],
            'unit_id'        => ['nullable', 'exists:units_of_measure,id'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $withdrawal = $this->service->withdrawConsignmentStock($validated);

        return $this->created($withdrawal);
    }

    /**
     * List consignment settlements with optional filters.
     */
    public function settlements(Request $request): JsonResponse
    {
        $query = VendorConsignmentSettlement::with(['vendor', 'bill'])
            ->when($request->vendor_id, fn($q, $id) => $q->where('vendor_id', (int) $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->from, fn($q, $d) => $q->where('settlement_period_from', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->where('settlement_period_to', '<=', $d))
            ->orderByDesc('settlement_period_from');

        $settlements = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($settlements);
    }

    /**
     * Create a new consignment settlement for a vendor and period.
     */
    public function createSettlement(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $validated = $request->validate([
            'vendor_id'   => [
                'required',
                Rule::exists('contacts', 'id')->where('organization_id', $organizationId),
            ],
            'period_from' => ['required', 'date'],
            'period_to'   => ['required', 'date', 'after_or_equal:period_from'],
        ]);

        $settlement = $this->service->createSettlement(
            (int) $validated['vendor_id'],
            $validated['period_from'],
            $validated['period_to']
        );

        return $this->created($settlement);
    }

    /**
     * Submit a draft settlement and create a vendor bill.
     */
    public function submitSettlement(int $id): JsonResponse
    {
        $settlement = VendorConsignmentSettlement::findOrFail($id);

        $this->service->submitSettlement($settlement);

        return $this->success($settlement->fresh(['vendor', 'bill']));
    }
}
