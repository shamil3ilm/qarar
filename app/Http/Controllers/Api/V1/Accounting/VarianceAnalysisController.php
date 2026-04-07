<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\VarianceAnalysisRun;
use App\Services\Accounting\VarianceAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VarianceAnalysisController extends Controller
{
    public function __construct(
        private readonly VarianceAnalysisService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = VarianceAnalysisRun::orderBy('id', 'desc')
            ->when($request->filled('period'), fn($q) => $q->where('period', $request->integer('period')))
            ->when($request->filled('fiscal_year'), fn($q) => $q->where('fiscal_year', $request->integer('fiscal_year')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')));

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer'],
            'run_type'    => ['required', Rule::in([
                VarianceAnalysisRun::RUN_TYPE_PRODUCTION_ORDER,
                VarianceAnalysisRun::RUN_TYPE_COST_CENTER,
                VarianceAnalysisRun::RUN_TYPE_PROJECT,
            ])],
        ]);

        $run = $this->service->runAnalysis(
            (int) $validated['period'],
            (int) $validated['fiscal_year'],
            $validated['run_type'],
            (int) $orgId,
            $request->user()->id
        );

        return $this->created($run);
    }

    public function show(int $id): JsonResponse
    {
        $run = VarianceAnalysisRun::with('runBy:id,name')->findOrFail($id);

        return $this->success($run);
    }

    public function results(int $id): JsonResponse
    {
        VarianceAnalysisRun::findOrFail($id);

        $items = $this->service->getResults($id);

        return $this->success($items);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer'],
        ]);

        $summary = $this->service->getSummaryByCategory(
            (int) $validated['period'],
            (int) $validated['fiscal_year']
        );

        return $this->success($summary);
    }
}
