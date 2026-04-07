<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\StatisticalKeyFigure;
use App\Services\Accounting\StatisticalKeyFigureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatisticalKeyFigureController extends Controller
{
    public function __construct(
        private readonly StatisticalKeyFigureService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['is_active', 'search']);
        $perPage = $request->integer('per_page', 20);

        return $this->paginated($this->service->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'            => ['required', 'string', 'max:20'],
            'name'            => ['required', 'string', 'max:100'],
            'unit_of_measure' => ['required', 'string', 'max:20'],
            'skf_type'        => ['nullable', Rule::in([StatisticalKeyFigure::TYPE_FIXED, StatisticalKeyFigure::TYPE_TOTAL])],
            'is_active'       => ['nullable', 'boolean'],
            'description'     => ['nullable', 'string'],
        ]);

        $skf = $this->service->create(array_merge($validated, ['organization_id' => $orgId]));

        return $this->created($skf);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $skf = StatisticalKeyFigure::with('values')->findOrFail($id);

        return $this->success($skf);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $skf = StatisticalKeyFigure::findOrFail($id);

        $validated = $request->validate([
            'code'            => ['sometimes', 'string', 'max:20'],
            'name'            => ['sometimes', 'string', 'max:100'],
            'unit_of_measure' => ['sometimes', 'string', 'max:20'],
            'skf_type'        => ['sometimes', Rule::in([StatisticalKeyFigure::TYPE_FIXED, StatisticalKeyFigure::TYPE_TOTAL])],
            'is_active'       => ['sometimes', 'boolean'],
            'description'     => ['nullable', 'string'],
        ]);

        $skf = $this->service->update($skf, $validated);

        return $this->success($skf);
    }

    public function destroy(int $id): JsonResponse
    {
        $skf = StatisticalKeyFigure::findOrFail($id);
        $skf->delete();

        return $this->noContent();
    }

    public function postValue(Request $request, int $id): JsonResponse
    {
        $orgId = $this->organizationId($request);

        StatisticalKeyFigure::findOrFail($id);

        $validated = $request->validate([
            'cost_center_id'   => ['nullable', 'integer', 'exists:cost_centers,id'],
            'profit_center_id' => ['nullable', 'integer', 'exists:profit_centers,id'],
            'period'           => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year'      => ['required', 'integer', 'min:2000', 'max:2099'],
            'value'            => ['required', 'numeric'],
        ]);

        $value = $this->service->postValue(array_merge($validated, [
            'organization_id'          => $orgId,
            'statistical_key_figure_id'=> $id,
            'posted_by'                => $request->user()?->id,
        ]));

        return $this->success($value);
    }

    public function periodValues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer'],
        ]);

        $values = $this->service->getValuesForPeriod(
            (int) $validated['period'],
            (int) $validated['fiscal_year']
        );

        return $this->success($values);
    }
}
