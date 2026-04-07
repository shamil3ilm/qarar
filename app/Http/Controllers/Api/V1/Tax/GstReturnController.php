<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tax;

use App\Http\Controllers\Controller;
use App\Models\Core\Organization;
use App\Models\Tax\GstReturn;
use App\Models\User;
use App\Services\Tax\GstComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles India GST return filings (GSTR-1 and GSTR-3B) against the
 * canonical gst_returns table introduced in migration 2026_03_25_000002.
 *
 * This controller is distinct from GstController, which manages GST
 * registrations, e-way bills, and the ITC ledger.
 */
class GstReturnController extends Controller
{
    public function __construct(
        private readonly GstComplianceService $gstReturnService
    ) {}

    /**
     * List GST returns for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GstReturn::where('organization_id', auth()->user()->organization_id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->when($request->filled('return_type'), fn($q) => $q->where('return_type', $request->input('return_type')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('year'), fn($q) => $q->where('period_year', $request->integer('year')));

        $returns = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($returns);
    }

    /**
     * Generate a GSTR-1 return for a given year and month.
     */
    public function generateGstr1(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year'   => ['required', 'integer', 'min:2017'],
            'month'  => ['required', 'integer', 'min:1', 'max:12'],
            'gstin'  => ['nullable', 'string', 'max:15'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        $organization = Organization::findOrFail(auth()->user()->organization_id);

        $return = $this->gstReturnService->generateGstr1(
            $organization,
            (int) $validated['year'],
            (int) $validated['month'],
        );

        return $this->success($return, 'GSTR-1 generated', 201);
    }

    /**
     * Generate a GSTR-3B return for a given year and month.
     */
    public function generateGstr3b(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year'   => ['required', 'integer', 'min:2017'],
            'month'  => ['required', 'integer', 'min:1', 'max:12'],
            'gstin'  => ['nullable', 'string', 'max:15'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        $organization = Organization::findOrFail(auth()->user()->organization_id);

        $return = $this->gstReturnService->generateGstr3b(
            $organization,
            (int) $validated['year'],
            (int) $validated['month'],
        );

        return $this->success($return, 'GSTR-3B generated', 201);
    }

    /**
     * Show a single GST return.
     */
    public function show(int $id): JsonResponse
    {
        $return = GstReturn::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);

        return $this->success($return);
    }

    /**
     * File a GST return (marks it as filed with a reference number).
     */
    public function file(Request $request, int $id): JsonResponse
    {
        $return = GstReturn::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);

        if ($return->isFiled()) {
            return $this->error('This return has already been filed.', 'ALREADY_FILED', 422);
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $return = $this->gstReturnService->fileReturn($return, $user);

        $return->update([
            'reference_number' => $validated['reference_number'],
            'notes'            => $validated['notes'] ?? $return->notes,
        ]);

        return $this->success($return->fresh(), 'GST return filed successfully');
    }
}
