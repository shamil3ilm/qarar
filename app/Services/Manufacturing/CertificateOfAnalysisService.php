<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CertificateOfAnalysis;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\InspectionResult;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CertificateOfAnalysisService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator
    ) {}

    /**
     * Paginated listing with optional filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = CertificateOfAnalysis::with(['product', 'contact', 'issuedByUser', 'approvedByUser'])
            ->when(!empty($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->when(!empty($filters['status']),     fn ($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['contact_id']), fn ($q) => $q->where('contact_id', $filters['contact_id']))
            ->when(!empty($filters['from']),        fn ($q) => $q->whereDate('issue_date', '>=', $filters['from']))
            ->when(!empty($filters['to']),          fn ($q) => $q->whereDate('issue_date', '<=', $filters['to']))
            ->orderByDesc('issue_date');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a new Certificate of Analysis.
     *
     * Calculates overall_result from the test_results array:
     *   if any test has pass_fail = 'fail' → 'fail'
     *   if any test has pass_fail = 'conditional' → 'conditional'
     *   otherwise → 'pass'
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): CertificateOfAnalysis
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['certificate_number'])) {
                $data['certificate_number'] = $this->numberGenerator->generate('COA');
            }

            if (!empty($data['test_results'])) {
                $data['overall_result'] = $this->calculateOverallResult($data['test_results']);
            }

            $data['issued_by'] = $data['issued_by'] ?? auth()->id();
            $data['issue_date'] = $data['issue_date'] ?? now()->toDateString();

            return CertificateOfAnalysis::create($data);
        });
    }

    /**
     * Approve a draft CoA.
     */
    public function approve(CertificateOfAnalysis $coa): void
    {
        if ($coa->status !== 'draft') {
            throw new InvalidArgumentException("Only draft certificates can be approved. Current status: {$coa->status}");
        }

        $coa->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
        ]);
    }

    /**
     * Issue an approved CoA, optionally linking it to a customer contact.
     */
    public function issue(CertificateOfAnalysis $coa, ?int $contactId = null): void
    {
        if ($coa->status !== 'approved') {
            throw new InvalidArgumentException("Only approved certificates can be issued. Current status: {$coa->status}");
        }

        $updates = ['status' => 'issued'];

        if ($contactId !== null) {
            $updates['contact_id'] = $contactId;
        }

        $coa->update($updates);
    }

    /**
     * Revoke an issued CoA with a reason.
     */
    public function revoke(CertificateOfAnalysis $coa, string $reason): void
    {
        if (!in_array($coa->status, ['approved', 'issued'], true)) {
            throw new InvalidArgumentException("Only approved or issued certificates can be revoked. Current status: {$coa->status}");
        }

        $remarks = trim(($coa->remarks ?? '') . "\nRevoked: {$reason}");

        $coa->update([
            'status'  => 'revoked',
            'remarks' => $remarks,
        ]);
    }

    /**
     * Auto-generate a CoA from an existing inspection lot.
     *
     * Pulls inspection results recorded against the lot and maps them
     * into the test_results JSON structure.
     */
    public function generateFromInspectionLot(int $inspectionLotId): CertificateOfAnalysis
    {
        $lot = InspectionLot::with(['results', 'qualityPlan'])->findOrFail($inspectionLotId);

        /** @var InspectionResult[]|\Illuminate\Support\Collection $results */
        $results = $lot->results ?? collect();

        $testResults = $results->map(fn ($r) => [
            'parameter'     => $r->characteristic ?? 'Parameter',
            'specification' => $r->specification   ?? '',
            'result'        => $r->actual_value     ?? '',
            'unit'          => $r->unit             ?? '',
            'pass_fail'     => $r->is_passed        ? 'pass' : 'fail',
        ])->toArray();

        $data = [
            'organization_id'   => $lot->organization_id,
            'product_id'        => $lot->product_id,
            'batch_number'      => $lot->batch_number ?? null,
            'inspection_lot_id' => $inspectionLotId,
            'issue_date'        => now()->toDateString(),
            'test_date'         => $lot->completed_at?->toDateString() ?? now()->toDateString(),
            'test_results'      => $testResults,
            'overall_result'    => $this->calculateOverallResult($testResults),
            'status'            => 'draft',
        ];

        return $this->store($data);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $testResults
     */
    private function calculateOverallResult(array $testResults): string
    {
        $hasConditional = false;

        foreach ($testResults as $result) {
            $pf = strtolower((string) ($result['pass_fail'] ?? 'pass'));

            if ($pf === 'fail') {
                return 'fail';
            }

            if ($pf === 'conditional') {
                $hasConditional = true;
            }
        }

        return $hasConditional ? 'conditional' : 'pass';
    }
}
