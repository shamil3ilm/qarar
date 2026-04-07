<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CoDistributionCycle;
use App\Services\Accounting\DistributionCycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DistributionCycleController extends Controller
{
    public function __construct(
        private readonly DistributionCycleService $service
    ) {}

    /**
     * List distribution cycles with optional fiscal year / status filter.
     *
     * GET /controlling/distribution-cycles
     */
    public function index(Request $request): JsonResponse
    {
        $query = CoDistributionCycle::with('executedBy:id,name')
            ->where('organization_id', $this->organizationId($request))
            ->orderByDesc('fiscal_year')
            ->orderBy('name')
            ->when($request->filled('fiscal_year'), fn($q) => $q->where('fiscal_year', $request->integer('fiscal_year')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status));

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    /**
     * Create a new distribution cycle.
     *
     * POST /controlling/distribution-cycles
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_from' => ['required', 'integer', 'min:1', 'max:12'],
            'period_to'   => ['required', 'integer', 'min:1', 'max:12', 'gte:period_from'],
        ]);

        $data['organization_id'] = $this->organizationId($request);
        $data['status']          = CoDistributionCycle::STATUS_OPEN;

        $cycle = CoDistributionCycle::create($data);

        return $this->created($cycle, 'Distribution cycle created.');
    }

    /**
     * Show a distribution cycle with its segments and postings.
     *
     * GET /controlling/distribution-cycles/{cycle}
     */
    public function show(CoDistributionCycle $distributionCycle): JsonResponse
    {
        $distributionCycle->load([
            'segments.receivers',
            'postings',
            'executedBy:id,name',
        ]);

        return $this->success($distributionCycle);
    }

    /**
     * Update an open distribution cycle.
     *
     * PUT /controlling/distribution-cycles/{cycle}
     */
    public function update(Request $request, CoDistributionCycle $distributionCycle): JsonResponse
    {
        if (! $distributionCycle->isOpen()) {
            return $this->error('Only open cycles can be updated.', 'CYCLE_NOT_OPEN', 422);
        }

        $data = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:150'],
            'period_from' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
            'period_to'   => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
        ]);

        $distributionCycle->update($data);

        return $this->success($distributionCycle->fresh(), 'Distribution cycle updated.');
    }

    /**
     * Delete an open distribution cycle.
     *
     * DELETE /controlling/distribution-cycles/{cycle}
     */
    public function destroy(CoDistributionCycle $distributionCycle): JsonResponse
    {
        if (! $distributionCycle->isOpen()) {
            return $this->error('Only open cycles can be deleted.', 'CYCLE_NOT_OPEN', 422);
        }

        $distributionCycle->delete();

        return $this->success(null, 'Distribution cycle deleted.');
    }

    /**
     * Execute the distribution cycle for a given period.
     * Redistributes primary costs from sender CC to receivers using cost elements.
     *
     * POST /controlling/distribution-cycles/{cycle}/execute
     */
    public function execute(Request $request, CoDistributionCycle $distributionCycle): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            $result = $this->service->execute($distributionCycle, (int) $data['period']);
            return $this->success([
                'postings_created' => count($result['postings']),
                'postings'         => $result['postings'],
            ], 'Distribution cycle executed.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_FAILED', 422);
        }
    }

    /**
     * Reverse the distribution cycle for a given period.
     *
     * POST /controlling/distribution-cycles/{cycle}/reverse
     */
    public function reverse(Request $request, CoDistributionCycle $distributionCycle): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            $this->service->reverse($distributionCycle, (int) $data['period']);
            return $this->success(null, 'Distribution cycle reversed.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_FAILED', 422);
        }
    }

    /**
     * List postings for a distribution cycle.
     *
     * GET /controlling/distribution-cycles/{cycle}/postings
     */
    public function postings(Request $request, CoDistributionCycle $distributionCycle): JsonResponse
    {
        $query = $distributionCycle->postings()
            ->with([
                'senderCostCenter:id,code,name',
                'receiverCostCenter:id,code,name',
            ])
            ->orderBy('period')
            ->when($request->filled('period'), fn($q) => $q->where('period', $request->integer('period')));

        return $this->paginated($query->paginate($request->integer('per_page', 50)));
    }
}
