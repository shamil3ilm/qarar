<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tax;

use App\Http\Controllers\Controller;
use App\Models\Core\Organization;
use App\Models\Tax\VatReturn;
use App\Models\User;
use App\Services\Tax\VatComplianceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages GCC VAT returns stored in the vat_return_periods table
 * (migration 2026_03_25_000002).
 *
 * Provides index, generate, show, and file actions.
 */
class VatComplianceController extends Controller
{
    public function __construct(
        private readonly VatComplianceService $vatComplianceService
    ) {}

    /**
     * List VAT return periods for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VatReturn::where('organization_id', auth()->user()->organization_id)
            ->with('lineItems')
            ->orderByDesc('period_start')
            ->when($request->filled('country_code'), fn($q) => $q->where('country_code', strtoupper($request->input('country_code'))))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')));

        $periods = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($periods);
    }

    /**
     * Generate (aggregate) a new VAT return period.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after:period_start'],
        ]);

        $organization = Organization::findOrFail(auth()->user()->organization_id);

        $period = $this->vatComplianceService->generateReturn(
            $organization,
            strtoupper($validated['country_code']),
            Carbon::parse($validated['period_start']),
            Carbon::parse($validated['period_end']),
        );

        return $this->success($period, 'VAT return generated', 201);
    }

    /**
     * Show a single VAT return period with its line items.
     */
    public function show(int $id): JsonResponse
    {
        $period = VatReturn::where('organization_id', auth()->user()->organization_id)
            ->with(['lineItems', 'filedBy'])
            ->findOrFail($id);

        return $this->success($period);
    }

    /**
     * File a VAT return (mark as filed).
     */
    public function file(int $id): JsonResponse
    {
        $period = VatReturn::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);

        if (in_array($period->status, ['filed', 'paid'], true)) {
            return $this->error('VAT return has already been filed.', 'ALREADY_FILED', 422);
        }

        /** @var User $user */
        $user = auth()->user();

        $period = $this->vatComplianceService->fileReturn($period, $user);

        return $this->success($period, 'VAT return filed successfully');
    }
}
