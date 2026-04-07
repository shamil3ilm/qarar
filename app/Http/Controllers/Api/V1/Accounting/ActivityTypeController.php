<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ActivityType;
use App\Services\Accounting\ActivityTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityTypeController extends Controller
{
    public function __construct(
        private readonly ActivityTypeService $service
    ) {}

    /**
     * List activity types with optional filters.
     *
     * GET /activity-types
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'is_active', 'cost_element_id', 'per_page']);

        return $this->paginated($this->service->index($filters));
    }

    /**
     * Create a new activity type.
     *
     * POST /activity-types
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'            => ['required', 'string', 'max:20'],
            'name'            => ['required', 'string', 'max:150'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'cost_element_id' => ['nullable', 'integer', 'exists:cost_elements,id'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $activityType = $this->service->store(array_merge($validated, ['organization_id' => $orgId]));

        return $this->created($activityType->load('costElement:id,code,name'));
    }

    /**
     * Show a single activity type with its rates.
     *
     * GET /activity-types/{activityType}
     */
    public function show(ActivityType $activityType): JsonResponse
    {
        return $this->success(
            $activityType->load([
                'costElement:id,code,name',
                'activityRates.costCenter:id,code,name',
                'activityRates.fiscalYear:id,name',
            ])
        );
    }

    /**
     * Update an activity type.
     *
     * PUT /activity-types/{activityType}
     */
    public function update(Request $request, ActivityType $activityType): JsonResponse
    {
        $validated = $request->validate([
            'code'            => ['sometimes', 'required', 'string', 'max:20'],
            'name'            => ['sometimes', 'required', 'string', 'max:150'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'cost_element_id' => ['nullable', 'integer', 'exists:cost_elements,id'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $updated = $this->service->update($activityType, $validated);

        return $this->success($updated);
    }

    /**
     * Soft-delete an activity type.
     *
     * DELETE /activity-types/{activityType}
     */
    public function destroy(ActivityType $activityType): JsonResponse
    {
        $this->service->destroy($activityType);

        return $this->success(['message' => 'Activity type deleted.']);
    }

    /**
     * Upsert an activity rate for a cost center / fiscal year / period.
     *
     * POST /activity-types/{activityType}/rates
     */
    public function setRate(Request $request, ActivityType $activityType): JsonResponse
    {
        $validated = $request->validate([
            'cost_center_id' => ['required', 'integer', 'exists:cost_centers,id'],
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'period'         => ['required', 'integer', 'min:1', 'max:12'],
            'planned_rate'   => ['nullable', 'numeric', 'min:0'],
            'actual_rate'    => ['nullable', 'numeric', 'min:0'],
            'currency_code'  => ['nullable', 'string', 'size:3'],
        ]);

        $rate = $this->service->setRate($activityType, $validated);

        return $this->success($rate);
    }
}
