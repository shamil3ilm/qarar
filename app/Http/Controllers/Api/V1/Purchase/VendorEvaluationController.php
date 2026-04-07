<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\SupplierScorecard;
use App\Services\Purchase\VendorEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor/Supplier Evaluation — SAP MM ME61.
 */
class VendorEvaluationController extends Controller
{
    public function __construct(private readonly VendorEvaluationService $service) {}

    // ----------------------------------------------------------------
    // Criteria management
    // ----------------------------------------------------------------

    /** GET /vendor-evaluation/criteria */
    public function criteria(Request $request): JsonResponse
    {
        $criteria = $this->service->getCriteria($request->user()->organization_id);

        return $this->successResponse($criteria, 'Evaluation criteria retrieved');
    }

    /** POST /vendor-evaluation/criteria */
    public function storeCriterion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'category'       => ['required', 'in:quality,delivery,price,service,compliance'],
            'weight_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $criterion = $this->service->createCriterion($request->user()->organization_id, $data);

        return $this->successResponse($criterion, 'Evaluation criterion created', 201);
    }

    // ----------------------------------------------------------------
    // Scorecards
    // ----------------------------------------------------------------

    /** GET /vendor-evaluation/scorecards */
    public function index(Request $request): JsonResponse
    {
        $scorecards = $this->service->listScorecards(
            organizationId: $request->user()->organization_id,
            filters:        $request->only(['supplier_id', 'status']),
            perPage:        (int) $request->get('per_page', 20),
        );

        return $this->paginatedResponse($scorecards, 'Supplier scorecards retrieved');
    }

    /** POST /vendor-evaluation/scorecards */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'  => ['required', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $scorecard = $this->service->createScorecard(
            organizationId: $request->user()->organization_id,
            supplierId:     $data['supplier_id'],
            periodStart:    $data['period_start'],
            periodEnd:      $data['period_end'],
            createdBy:      $request->user()->id,
        );

        return $this->successResponse($scorecard, 'Supplier scorecard created', 201);
    }

    /** GET /vendor-evaluation/scorecards/{scorecard} */
    public function show(SupplierScorecard $supplierScorecard): JsonResponse
    {
        return $this->successResponse(
            $supplierScorecard->load(['supplier:id,name', 'ratings.criterion', 'evaluator:id,name']),
            'Scorecard retrieved',
        );
    }

    /** PUT /vendor-evaluation/scorecards/{scorecard}/ratings */
    public function updateRatings(Request $request, SupplierScorecard $supplierScorecard): JsonResponse
    {
        $data = $request->validate([
            'ratings'               => ['required', 'array', 'min:1'],
            'ratings.*.criterion_id' => ['required', 'integer'],
            'ratings.*.score'       => ['required', 'numeric', 'min:0', 'max:100'],
            'ratings.*.comments'    => ['nullable', 'string'],
        ]);

        $scorecard = $this->service->updateRatings($supplierScorecard, $data['ratings']);

        return $this->successResponse($scorecard, 'Ratings updated');
    }

    /** POST /vendor-evaluation/scorecards/{scorecard}/finalize */
    public function finalize(Request $request, SupplierScorecard $supplierScorecard): JsonResponse
    {
        $scorecard = $this->service->finalize($supplierScorecard, $request->user()->id);

        return $this->successResponse($scorecard, 'Scorecard finalized');
    }

    // ----------------------------------------------------------------
    // Reporting
    // ----------------------------------------------------------------

    /** GET /vendor-evaluation/ranking */
    public function ranking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date'],
        ]);

        $ranking = $this->service->supplierRanking(
            $request->user()->organization_id,
            $data['period_start'],
            $data['period_end'],
        );

        return $this->successResponse($ranking, 'Supplier ranking retrieved');
    }

    /** GET /vendor-evaluation/suppliers/{supplierId}/trend */
    public function trend(Request $request, int $supplierId): JsonResponse
    {
        $trend = $this->service->supplierTrend(
            $request->user()->organization_id,
            $supplierId,
        );

        return $this->successResponse($trend, 'Supplier score trend retrieved');
    }
}
