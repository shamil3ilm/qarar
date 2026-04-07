<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\CertificateOfAnalysis;
use App\Services\Manufacturing\CertificateOfAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateOfAnalysisController extends Controller
{
    public function __construct(
        private readonly CertificateOfAnalysisService $service
    ) {}

    /**
     * GET /manufacturing/quality/certificates-of-analysis
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['nullable', 'integer'],
            'status'     => ['nullable', 'string', 'in:draft,approved,issued,revoked'],
            'contact_id' => ['nullable', 'integer'],
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date', 'after_or_equal:from'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->service->index($request->all());

        return $this->success($paginator, 'Certificates of analysis retrieved.');
    }

    /**
     * POST /manufacturing/quality/certificates-of-analysis
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'        => ['required', 'integer', 'exists:products,id'],
            'batch_number'      => ['nullable', 'string', 'max:100'],
            'inspection_lot_id' => ['nullable', 'integer'],
            'contact_id'        => ['nullable', 'integer', 'exists:contacts,id'],
            'issue_date'        => ['nullable', 'date'],
            'test_date'         => ['nullable', 'date'],
            'test_results'      => ['required', 'array', 'min:1'],
            'test_results.*.parameter'     => ['required', 'string'],
            'test_results.*.specification' => ['nullable', 'string'],
            'test_results.*.result'        => ['required', 'string'],
            'test_results.*.unit'          => ['nullable', 'string'],
            'test_results.*.pass_fail'     => ['required', 'string', 'in:pass,fail,conditional'],
            'remarks'           => ['nullable', 'string'],
            'certificate_number' => ['nullable', 'string', 'max:30'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $coa = $this->service->store($validated);

        return $this->created($coa->load(['product', 'issuedByUser']), 'Certificate of analysis created.');
    }

    /**
     * GET /manufacturing/quality/certificates-of-analysis/{certificateOfAnalysis}
     */
    public function show(CertificateOfAnalysis $certificateOfAnalysis): JsonResponse
    {
        return $this->success(
            $certificateOfAnalysis->load(['product', 'contact', 'issuedByUser', 'approvedByUser']),
            'Certificate of analysis retrieved.'
        );
    }

    /**
     * PUT /manufacturing/quality/certificates-of-analysis/{certificateOfAnalysis}
     */
    public function update(Request $request, CertificateOfAnalysis $certificateOfAnalysis): JsonResponse
    {
        if ($certificateOfAnalysis->status !== 'draft') {
            return $this->error('Only draft certificates can be updated.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'batch_number'      => ['nullable', 'string', 'max:100'],
            'contact_id'        => ['nullable', 'integer', 'exists:contacts,id'],
            'issue_date'        => ['nullable', 'date'],
            'test_date'         => ['nullable', 'date'],
            'test_results'      => ['nullable', 'array', 'min:1'],
            'test_results.*.parameter'     => ['required_with:test_results', 'string'],
            'test_results.*.specification' => ['nullable', 'string'],
            'test_results.*.result'        => ['required_with:test_results', 'string'],
            'test_results.*.unit'          => ['nullable', 'string'],
            'test_results.*.pass_fail'     => ['required_with:test_results', 'string', 'in:pass,fail,conditional'],
            'remarks'           => ['nullable', 'string'],
        ]);

        $certificateOfAnalysis->update($validated);

        return $this->success($certificateOfAnalysis->fresh(), 'Certificate of analysis updated.');
    }

    /**
     * DELETE /manufacturing/quality/certificates-of-analysis/{certificateOfAnalysis}
     */
    public function destroy(CertificateOfAnalysis $certificateOfAnalysis): JsonResponse
    {
        if ($certificateOfAnalysis->status !== 'draft') {
            return $this->error('Only draft certificates can be deleted.', 'INVALID_STATUS', 422);
        }

        $certificateOfAnalysis->delete();

        return $this->success(null, 'Certificate of analysis deleted.');
    }

    /**
     * POST /manufacturing/quality/certificates-of-analysis/{certificateOfAnalysis}/approve
     */
    public function approve(CertificateOfAnalysis $certificateOfAnalysis): JsonResponse
    {
        $this->service->approve($certificateOfAnalysis);

        return $this->success($certificateOfAnalysis->fresh(), 'Certificate approved.');
    }

    /**
     * POST /manufacturing/quality/certificates-of-analysis/{certificateOfAnalysis}/issue
     * Body (optional): { contact_id }
     */
    public function issue(Request $request, CertificateOfAnalysis $certificateOfAnalysis): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ]);

        $this->service->issue($certificateOfAnalysis, $validated['contact_id'] ?? null);

        return $this->success($certificateOfAnalysis->fresh(), 'Certificate issued.');
    }

    /**
     * POST /manufacturing/quality/certificates-of-analysis/{certificateOfAnalysis}/revoke
     * Body: { reason }
     */
    public function revoke(Request $request, CertificateOfAnalysis $certificateOfAnalysis): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->service->revoke($certificateOfAnalysis, $validated['reason']);

        return $this->success($certificateOfAnalysis->fresh(), 'Certificate revoked.');
    }

    /**
     * POST /manufacturing/quality/certificates-of-analysis/from-lot
     * Body: { inspection_lot_id }
     */
    public function generateFromLot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inspection_lot_id' => ['required', 'integer', 'min:1'],
        ]);

        $coa = $this->service->generateFromInspectionLot((int) $validated['inspection_lot_id']);

        return $this->created(
            $coa->load(['product', 'issuedByUser']),
            'Certificate of analysis generated from inspection lot.'
        );
    }
}
