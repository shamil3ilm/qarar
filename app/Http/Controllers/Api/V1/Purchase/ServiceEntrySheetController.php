<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\ServiceEntrySheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceEntrySheetController extends Controller
{
    /**
     * List service entry sheets with filters.
     * SAP equivalent: ML81N (Service Entry Sheet list)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceEntrySheet::with(['vendor', 'servicePurchaseOrder', 'submittedBy', 'approvedBy'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->vendor_id, fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($request->service_purchase_order_id, fn ($q, $id) => $q->where('service_purchase_order_id', $id))
            ->when($request->search, fn ($q, $search) => $q->where('ses_number', 'like', "%{$search}%"))
            ->when($request->from_date, fn ($q, $date) => $q->where('service_period_from', '>=', $date))
            ->when($request->to_date, fn ($q, $date) => $q->where('service_period_to', '<=', $date))
            ->orderByDesc('created_at');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * Create a new service entry sheet.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_purchase_order_id' => ['required', 'integer', 'exists:service_purchase_orders,id'],
            'vendor_id'                 => ['required', 'integer'],
            'service_period_from'       => ['required', 'date'],
            'service_period_to'         => ['required', 'date', 'after_or_equal:service_period_from'],
            'description'               => ['nullable', 'string', 'max:1000'],
            'lines'                     => ['required', 'array', 'min:1'],
            'lines.*.service_po_line_id' => ['required', 'integer'],
            'lines.*.quantity'           => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price'         => ['required', 'numeric', 'min:0'],
            'lines.*.description'        => ['nullable', 'string', 'max:500'],
        ]);

        $sheet = ServiceEntrySheet::create([
            'organization_id'           => $request->user()->organization_id,
            'service_purchase_order_id' => $validated['service_purchase_order_id'],
            'vendor_id'                 => $validated['vendor_id'],
            'service_period_from'       => $validated['service_period_from'],
            'service_period_to'         => $validated['service_period_to'],
            'description'               => $validated['description'] ?? null,
            'status'                    => ServiceEntrySheet::STATUS_DRAFT,
            'submitted_by'              => null,
        ]);

        if (method_exists($sheet, 'lines') && isset($validated['lines'])) {
            foreach ($validated['lines'] as $line) {
                $sheet->lines()->create([
                    'service_po_line_id' => $line['service_po_line_id'],
                    'quantity'           => $line['quantity'],
                    'unit_price'         => $line['unit_price'],
                    'description'        => $line['description'] ?? null,
                    'total_amount'       => $line['quantity'] * $line['unit_price'],
                ]);
            }
        }

        $sheet->load(['vendor', 'servicePurchaseOrder', 'lines']);

        return $this->created($sheet);
    }

    /**
     * Get a single service entry sheet by UUID.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $sheet = ServiceEntrySheet::with(['vendor', 'servicePurchaseOrder', 'lines', 'submittedBy', 'approvedBy'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->success($sheet);
    }

    /**
     * Update a draft service entry sheet.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $sheet = ServiceEntrySheet::where('uuid', $uuid)->firstOrFail();

        if ($sheet->status !== ServiceEntrySheet::STATUS_DRAFT) {
            return $this->error('Only draft service entry sheets can be updated.', 422);
        }

        $validated = $request->validate([
            'service_period_from' => ['sometimes', 'date'],
            'service_period_to'   => ['sometimes', 'date', 'after_or_equal:service_period_from'],
            'description'         => ['nullable', 'string', 'max:1000'],
        ]);

        $sheet->update($validated);

        return $this->success($sheet->fresh(['vendor', 'servicePurchaseOrder', 'lines']));
    }

    /**
     * Submit a service entry sheet for approval.
     * SAP equivalent: posting SES for acceptance.
     */
    public function submit(Request $request, string $uuid): JsonResponse
    {
        $sheet = ServiceEntrySheet::where('uuid', $uuid)->firstOrFail();

        if ($sheet->status !== ServiceEntrySheet::STATUS_DRAFT) {
            return $this->error('Only draft service entry sheets can be submitted.', 422);
        }

        $sheet->update([
            'status'       => ServiceEntrySheet::STATUS_SUBMITTED,
            'submitted_by' => $request->user()->id,
        ]);

        return $this->success(
            $sheet->fresh(),
            'Service entry sheet submitted for approval.'
        );
    }

    /**
     * Accept or reject a submitted service entry sheet.
     * POST /service-entry-sheets/{uuid}/review  {"action": "accept"|"reject", "rejection_reason": "..."}
     */
    public function review(Request $request, string $uuid): JsonResponse
    {
        $sheet = ServiceEntrySheet::where('uuid', $uuid)->firstOrFail();

        if ($sheet->status !== ServiceEntrySheet::STATUS_SUBMITTED) {
            return $this->error('Only submitted service entry sheets can be reviewed.', 422);
        }

        $validated = $request->validate([
            'action'           => ['required', 'in:accept,reject'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['action'] === 'accept') {
            $sheet->update([
                'status'      => ServiceEntrySheet::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            return $this->success($sheet->fresh(), 'Service entry sheet accepted.');
        }

        $sheet->update([
            'status'           => ServiceEntrySheet::STATUS_REJECTED,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);
        return $this->success($sheet->fresh(), 'Service entry sheet rejected.');
    }
}
