<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tax;

use App\Http\Controllers\Controller;
use App\Models\Core\Organization;
use App\Models\Tax\TdsConfiguration;
use App\Models\Tax\TdsEntry;
use App\Services\Tax\TdsComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages TDS configurations and deductions stored in the tds_configurations
 * and tds_deductions tables (migration 2026_03_25_000002).
 *
 * Distinct from TdsController, which manages TDS sections, certificates,
 * quarterly returns, and TCS collections from the prior schema.
 */
class TdsComplianceController extends Controller
{
    public function __construct(
        private readonly TdsComplianceService $tdsComplianceService
    ) {}

    /**
     * List TDS section configurations for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TdsConfiguration::where('organization_id', auth()->user()->organization_id)
            ->orderBy('section_code')
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $configurations = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($configurations);
    }

    /**
     * List TDS deductions recorded for the organization.
     */
    public function listDeductions(Request $request): JsonResponse
    {
        $query = TdsEntry::where('organization_id', auth()->user()->organization_id)
            ->with('tdsSection')
            ->orderByDesc('transaction_date')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('deductee_type'), fn($q) => $q->where('deductee_type', $request->input('deductee_type')))
            ->when($request->filled('from_date'), fn($q) => $q->where('transaction_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn($q) => $q->where('transaction_date', '<=', $request->input('to_date')));

        $deductions = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($deductions);
    }

    /**
     * Record a new TDS deduction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deductee_type'       => ['required', 'in:vendor,employee,contractor,other'],
            'deductee_id'         => ['nullable', 'integer'],
            'tds_section_id'      => ['nullable', 'integer', 'exists:tds_configurations,id'],
            'pan_number'          => ['nullable', 'string', 'size:10'],
            'transaction_date'    => ['required', 'date'],
            'transaction_amount'  => ['required', 'numeric', 'min:0'],
            'tds_rate'            => ['required', 'numeric', 'min:0', 'max:100'],
            'nature_of_payment'   => ['nullable', 'string', 'max:100'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ]);

        $tdsAmount = (float) bcmul(
            (string) $validated['transaction_amount'],
            bcdiv((string) $validated['tds_rate'], '100', 6),
            4
        );

        $deduction = $this->tdsComplianceService->recordDeduction(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id,
            'tds_amount'      => $tdsAmount,
        ]));

        return $this->success($deduction, 'TDS deduction recorded', 201);
    }

    /**
     * Mark a TDS deduction as deposited with a challan number.
     */
    public function deposit(Request $request, int $id): JsonResponse
    {
        $deduction = TdsEntry::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);

        if ($deduction->status === 'deposited') {
            return $this->error('TDS has already been deposited.', 'ALREADY_DEPOSITED', 422);
        }

        $validated = $request->validate([
            'challan_number' => ['required', 'string', 'max:50'],
        ]);

        $deduction = $this->tdsComplianceService->depositChallan($deduction, $validated['challan_number']);

        return $this->success($deduction, 'TDS deposited successfully');
    }

    /**
     * Return a report of all pending (undeposited) TDS deductions.
     */
    public function pendingReport(Request $request): JsonResponse
    {
        $organization = Organization::findOrFail(auth()->user()->organization_id);

        $pending = $this->tdsComplianceService->getPendingDeductions($organization);

        $summary = [
            'total_deductions'        => $pending->count(),
            'total_transaction_amount' => $pending->sum('transaction_amount'),
            'total_tds_amount'        => $pending->sum('tds_amount'),
            'by_deductee_type'        => $pending->groupBy('deductee_type')->map(fn ($group) => [
                'count'              => $group->count(),
                'transaction_amount' => $group->sum('transaction_amount'),
                'tds_amount'         => $group->sum('tds_amount'),
            ]),
        ];

        return $this->success([
            'deductions' => $pending,
            'summary'    => $summary,
        ]);
    }
}
