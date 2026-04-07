<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CoAssessmentCycle;
use App\Services\Accounting\AssessmentCycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class AssessmentCycleController extends Controller
{
    public function __construct(
        private readonly AssessmentCycleService $service
    ) {}

    /**
     * List assessment cycles with optional fiscal year filter.
     *
     * GET /controlling/assessment-cycles
     */
    public function index(Request $request): JsonResponse
    {
        $query = CoAssessmentCycle::with('executedBy:id,name')
            ->where('organization_id', $this->organizationId($request))
            ->orderByDesc('fiscal_year')
            ->orderBy('name')
            ->when($request->filled('fiscal_year'), fn($q) => $q->where('fiscal_year', $request->integer('fiscal_year')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status));

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    /**
     * Create a new assessment cycle.
     *
     * POST /controlling/assessment-cycles
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'cycle_type'  => ['nullable', 'in:assessment,distribution'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_from' => ['required', 'integer', 'min:1', 'max:12'],
            'period_to'   => ['required', 'integer', 'min:1', 'max:12', 'gte:period_from'],
        ]);

        $data['organization_id'] = $this->organizationId($request);
        $data['status']          = CoAssessmentCycle::STATUS_OPEN;

        $cycle = CoAssessmentCycle::create($data);

        return $this->created($cycle, 'Assessment cycle created.');
    }

    /**
     * Show a single assessment cycle with its segments and postings.
     *
     * GET /controlling/assessment-cycles/{cycle}
     */
    public function show(CoAssessmentCycle $assessmentCycle): JsonResponse
    {
        $assessmentCycle->load([
            'segments.receivers',
            'segments.statisticalKeyFigure:id,name',
            'postings',
            'executedBy:id,name',
        ]);

        return $this->success($assessmentCycle);
    }

    /**
     * Update an open assessment cycle.
     *
     * PUT /controlling/assessment-cycles/{cycle}
     */
    public function update(Request $request, CoAssessmentCycle $assessmentCycle): JsonResponse
    {
        if (! $assessmentCycle->isOpen()) {
            return $this->error('Only open cycles can be updated.', 'CYCLE_NOT_OPEN', 422);
        }

        $data = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'period_from' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
            'period_to'   => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
        ]);

        $assessmentCycle->update($data);

        return $this->success($assessmentCycle->fresh(), 'Assessment cycle updated.');
    }

    /**
     * Delete an open assessment cycle.
     *
     * DELETE /controlling/assessment-cycles/{cycle}
     */
    public function destroy(CoAssessmentCycle $assessmentCycle): JsonResponse
    {
        if (! $assessmentCycle->isOpen()) {
            return $this->error('Only open cycles can be deleted.', 'CYCLE_NOT_OPEN', 422);
        }

        $assessmentCycle->delete();

        return $this->success(null, 'Assessment cycle deleted.');
    }

    /**
     * Execute the cycle for a given period — creates CoAssessmentPosting rows
     * and auto-posts GL journal entries (receiver CC debit / sender CC credit).
     *
     * POST /controlling/assessment-cycles/{cycle}/execute
     */
    public function execute(Request $request, CoAssessmentCycle $assessmentCycle): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            $result = $this->service->execute($assessmentCycle, (int) $data['period']);
            return $this->success([
                'postings_created' => count($result['postings']),
                'postings'         => $result['postings'],
            ], 'Assessment cycle executed.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_FAILED', 422);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'EXECUTION_FAILED', 422);
        }
    }

    /**
     * Reverse the cycle for a given period — creates reversal postings.
     *
     * POST /controlling/assessment-cycles/{cycle}/reverse
     */
    public function reverse(Request $request, CoAssessmentCycle $assessmentCycle): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            $this->service->reverse($assessmentCycle, (int) $data['period']);
            return $this->success(null, 'Assessment cycle reversed.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_FAILED', 422);
        }
    }

    /**
     * List postings for a cycle (optionally filtered by period).
     *
     * GET /controlling/assessment-cycles/{cycle}/postings
     */
    public function postings(Request $request, CoAssessmentCycle $assessmentCycle): JsonResponse
    {
        $query = $assessmentCycle->postings()
            ->with([
                'senderCostCenter:id,code,name',
                'receiverCostCenter:id,code,name',
            ])
            ->orderBy('period')
            ->when($request->filled('period'), fn($q) => $q->where('period', $request->integer('period')));

        return $this->paginated($query->paginate($request->integer('per_page', 50)));
    }
}
