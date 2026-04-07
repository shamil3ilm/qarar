<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tax;

use App\Http\Controllers\Controller;
use App\Models\Tax\Ewaybill;
use App\Models\Tax\GstRegistration;
use App\Models\Tax\Gstr1Return;
use App\Models\Tax\Gstr3bReturn;
use App\Models\Tax\ItcLedger;
use App\Services\Tax\GstReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GstController extends Controller
{
    public function __construct(
        private readonly GstReturnService $gstReturnService
    ) {}

    // -------------------------------------------------------------------------
    // GST Registrations
    // -------------------------------------------------------------------------

    /**
     * List GST registrations for the organization.
     */
    public function indexRegistrations(Request $request): JsonResponse
    {
        $registrations = GstRegistration::where('organization_id', auth()->user()->organization_id)
            ->orderBy('gstin')
            ->get();

        return $this->success($registrations);
    }

    /**
     * Store a new GST registration.
     */
    public function storeRegistration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gstin'             => ['required', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'state_code'        => ['required', 'string', 'size:2'],
            'legal_name'        => ['required', 'string', 'max:200'],
            'trade_name'        => ['nullable', 'string', 'max:200'],
            'registration_date' => ['required', 'date'],
        ]);

        $organizationId = auth()->user()->organization_id;

        $exists = GstRegistration::where('organization_id', $organizationId)
            ->where('gstin', $validated['gstin'])
            ->exists();

        if ($exists) {
            return $this->error('GSTIN already registered for this organization.', 'DUPLICATE_GSTIN', 422);
        }

        $registration = GstRegistration::create(array_merge($validated, [
            'organization_id' => $organizationId,
            'is_active'       => true,
        ]));

        return $this->success($registration, 'GST registration created', 201);
    }

    // -------------------------------------------------------------------------
    // GSTR-1
    // -------------------------------------------------------------------------

    /**
     * Prepare a GSTR-1 return.
     */
    public function prepareGstr1(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gstin_id'    => ['required', 'integer', 'exists:gst_registrations,id'],
            'month'       => ['required', 'integer', 'min:1', 'max:12'],
            'year'        => ['required', 'integer', 'min:2020'],
            'filing_type' => ['nullable', 'in:monthly,quarterly'],
        ]);

        $registration = GstRegistration::where('id', $validated['gstin_id'])
            ->where('organization_id', auth()->user()->organization_id)
            ->firstOrFail();

        $return = $this->gstReturnService->prepareGstr1(
            $registration,
            $validated['month'],
            $validated['year'],
            $validated['filing_type'] ?? 'monthly'
        );

        $return->load('b2bInvoices');

        return $this->success($return, 'GSTR-1 prepared');
    }

    /**
     * File a GSTR-1 return.
     */
    public function fileGstr1(Request $request, Gstr1Return $gstr1Return): JsonResponse
    {
        $this->authorizeOrganization($gstr1Return->organization_id);

        $validated = $request->validate([
            'arn' => ['required', 'string', 'max:30'],
        ]);

        $return = $this->gstReturnService->fileGstr1($gstr1Return, $validated['arn']);

        return $this->success($return, 'GSTR-1 filed successfully');
    }

    // -------------------------------------------------------------------------
    // GSTR-3B
    // -------------------------------------------------------------------------

    /**
     * Prepare a GSTR-3B return.
     */
    public function prepareGstr3b(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gstin_id' => ['required', 'integer', 'exists:gst_registrations,id'],
            'month'    => ['required', 'integer', 'min:1', 'max:12'],
            'year'     => ['required', 'integer', 'min:2020'],
        ]);

        $registration = GstRegistration::where('id', $validated['gstin_id'])
            ->where('organization_id', auth()->user()->organization_id)
            ->firstOrFail();

        $return = $this->gstReturnService->prepareGstr3b(
            $registration,
            $validated['month'],
            $validated['year']
        );

        return $this->success($return, 'GSTR-3B prepared');
    }

    /**
     * File a GSTR-3B return.
     */
    public function fileGstr3b(Request $request, Gstr3bReturn $gstr3bReturn): JsonResponse
    {
        $this->authorizeOrganization($gstr3bReturn->organization_id);

        $validated = $request->validate([
            'arn' => ['required', 'string', 'max:30'],
        ]);

        $return = $this->gstReturnService->fileGstr3b($gstr3bReturn, $validated['arn']);

        return $this->success($return, 'GSTR-3B filed successfully');
    }

    // -------------------------------------------------------------------------
    // E-Way Bills
    // -------------------------------------------------------------------------

    /**
     * Generate an e-way bill.
     */
    public function generateEwayBill(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type'        => ['nullable', 'string', 'max:50'],
            'source_id'          => ['nullable', 'integer'],
            'gstin_supplier'     => ['required', 'string', 'max:15'],
            'gstin_recipient'    => ['required', 'string', 'max:15'],
            'supply_type'        => ['required', 'string', 'max:20'],
            'transporter_id'     => ['nullable', 'string', 'max:15'],
            'vehicle_number'     => ['nullable', 'string', 'max:15'],
            'distance_km'        => ['required', 'integer', 'min:1'],
        ]);

        $ewayBill = $this->gstReturnService->generateEwayBill(
            array_merge($validated, ['organization_id' => auth()->user()->organization_id])
        );

        return $this->success($ewayBill, 'E-way bill generated', 201);
    }

    /**
     * Show an e-way bill.
     */
    public function showEwayBill(Ewaybill $ewaybill): JsonResponse
    {
        $this->authorizeOrganization($ewaybill->organization_id);

        return $this->success($ewaybill);
    }

    /**
     * Cancel an e-way bill.
     */
    public function cancelEwayBill(Request $request, Ewaybill $ewaybill): JsonResponse
    {
        $this->authorizeOrganization($ewaybill->organization_id);

        if ($ewaybill->isCancelled()) {
            return $this->error('E-way bill is already cancelled.', 'ALREADY_CANCELLED', 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $ewayBill = $this->gstReturnService->cancelEwayBill($ewaybill, $validated['reason'] ?? '');

        return $this->success($ewayBill, 'E-way bill cancelled');
    }

    /**
     * List e-way bills for the organization.
     */
    public function indexEwayBills(Request $request): JsonResponse
    {
        $query = Ewaybill::where('organization_id', auth()->user()->organization_id)
            ->orderByDesc('generated_at')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')));

        $ewayBills = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($ewayBills);
    }

    // -------------------------------------------------------------------------
    // ITC Ledger
    // -------------------------------------------------------------------------

    /**
     * View ITC ledger entries.
     */
    public function itcLedger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gstin_id' => ['required', 'integer', 'exists:gst_registrations,id'],
            'month'    => ['nullable', 'integer', 'min:1', 'max:12'],
            'year'     => ['nullable', 'integer', 'min:2020'],
        ]);

        $registration = GstRegistration::where('id', $validated['gstin_id'])
            ->where('organization_id', auth()->user()->organization_id)
            ->firstOrFail();

        $query = ItcLedger::where('gstin_id', $registration->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month');

        if (!empty($validated['month']) && !empty($validated['year'])) {
            $query->forPeriod($validated['month'], $validated['year']);
        }

        $ledger = $query->paginate($request->integer('per_page', 12));

        return $this->paginated($ledger);
    }

    /**
     * Update / create ITC ledger entry.
     */
    public function updateItcLedger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gstin_id'        => ['required', 'integer', 'exists:gst_registrations,id'],
            'month'           => ['required', 'integer', 'min:1', 'max:12'],
            'year'            => ['required', 'integer', 'min:2020'],
            'igst_available'  => ['required', 'numeric', 'min:0'],
            'cgst_available'  => ['required', 'numeric', 'min:0'],
            'sgst_available'  => ['required', 'numeric', 'min:0'],
            'igst_utilized'   => ['required', 'numeric', 'min:0'],
            'cgst_utilized'   => ['required', 'numeric', 'min:0'],
            'sgst_utilized'   => ['required', 'numeric', 'min:0'],
            'igst_closing'    => ['required', 'numeric', 'min:0'],
            'cgst_closing'    => ['required', 'numeric', 'min:0'],
            'sgst_closing'    => ['required', 'numeric', 'min:0'],
        ]);

        $registration = GstRegistration::where('id', $validated['gstin_id'])
            ->where('organization_id', auth()->user()->organization_id)
            ->firstOrFail();

        $ledger = $this->gstReturnService->updateItcLedger(
            $registration,
            $validated['month'],
            $validated['year'],
            array_diff_key($validated, array_flip(['gstin_id', 'month', 'year']))
        );

        return $this->success($ledger, 'ITC ledger updated');
    }

    /**
     * Ensure the resource belongs to the authenticated user's organization.
     */
    private function authorizeOrganization(int $resourceOrganizationId): void
    {
        if ($resourceOrganizationId !== auth()->user()->organization_id) {
            abort(403, 'Access denied.');
        }
    }
}
