<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CoReposting;
use App\Services\Accounting\CoRepostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoRepostingController extends Controller
{
    public function __construct(
        private readonly CoRepostingService $service
    ) {}

    /**
     * List CO repostings with optional filters.
     *
     * GET /co-repostings
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'from_date', 'to_date', 'period', 'fiscal_year',
            'from_type', 'from_id', 'status', 'per_page',
        ]);

        return $this->paginated($this->service->list($filters));
    }

    /**
     * Create a new CO reposting (KB11N equivalent).
     *
     * POST /co-repostings
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'posting_date'    => ['required', 'date'],
            'document_date'   => ['nullable', 'date'],
            'period'          => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year'     => ['required', 'integer', 'min:2000', 'max:2100'],
            'from_type'       => ['required', Rule::in([
                CoReposting::FROM_COST_CENTER,
                CoReposting::FROM_INTERNAL_ORDER,
                CoReposting::FROM_PROFIT_CENTER,
            ])],
            'from_id'         => ['required', 'integer', 'min:1'],
            'to_type'         => ['required', Rule::in([
                CoReposting::FROM_COST_CENTER,
                CoReposting::FROM_INTERNAL_ORDER,
                CoReposting::FROM_PROFIT_CENTER,
            ])],
            'to_id'           => ['required', 'integer', 'min:1'],
            'cost_element_id' => ['required', 'integer', 'exists:cost_elements,id'],
            'amount'          => ['required', 'numeric', 'min:0.0001'],
            'currency_code'   => ['nullable', 'string', 'size:3'],
            'narration'       => ['nullable', 'string', 'max:1000'],
        ]);

        $reposting = $this->service->create(array_merge($validated, [
            'organization_id' => $orgId,
            'posted_by'       => $request->user()->id,
        ]));

        return $this->created($reposting);
    }

    /**
     * Show a single CO reposting.
     *
     * GET /co-repostings/{coReposting}
     */
    public function show(CoReposting $coReposting): JsonResponse
    {
        $coReposting->load(['costElement:id,code,name', 'postedBy:id,name', 'reversedBy:id,reposting_number']);

        return $this->success($coReposting);
    }

    /**
     * Soft-delete a CO reposting record (does NOT undo balance adjustments; use reverse for that).
     *
     * DELETE /co-repostings/{coReposting}
     */
    public function destroy(CoReposting $coReposting): JsonResponse
    {
        if ($coReposting->isReversed()) {
            return $this->error('Reversed repostings cannot be deleted.', 'DELETE_BLOCKED', 422);
        }

        $coReposting->delete();

        return $this->success(['message' => 'CO reposting deleted.']);
    }

    /**
     * Reverse a CO reposting — creates a mirror reposting and undoes balance adjustments.
     *
     * POST /co-repostings/{coReposting}/reverse
     */
    public function reverse(CoReposting $coReposting): JsonResponse
    {
        return $this->tryAction(fn() => $this->service->reverse($coReposting));
    }
}
