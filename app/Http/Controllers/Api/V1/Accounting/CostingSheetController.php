<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CostingSheet;
use App\Services\Accounting\CostingSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostingSheetController extends Controller
{
    public function __construct(
        private readonly CostingSheetService $service
    ) {}

    // ================================================================
    // Costing Sheets
    // ================================================================

    /**
     * List costing sheets.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CostingSheet::orderBy('code')
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q) use ($search): void {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });

        $perPage = $request->integer('per_page', 20);

        return $this->paginated($query->paginate($perPage));
    }

    /**
     * Create a new costing sheet.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'                        => ['required', 'string', 'max:30'],
            'name'                        => ['required', 'string', 'max:255'],
            'description'                 => ['nullable', 'string'],
            'cost_component_structure_id' => ['nullable', 'integer'],
            'is_active'                   => ['nullable', 'boolean'],
        ]);

        $sheet = $this->service->create(
            array_merge($validated, ['organization_id' => $orgId])
        );

        return $this->created($sheet);
    }

    /**
     * Show a single costing sheet with its rows.
     */
    public function show(int $id): JsonResponse
    {
        $sheet = CostingSheet::with([
            'rows.overheadKey:id,code,name',
            'rows.baseCostElement:id,code,name',
            'rows.creditCostCenter:id,code,name',
            'rows.creditCostElement:id,code,name',
        ])->findOrFail($id);

        return $this->success($sheet);
    }

    /**
     * Update a costing sheet.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sheet = CostingSheet::findOrFail($id);

        $validated = $request->validate([
            'code'                        => ['sometimes', 'required', 'string', 'max:30'],
            'name'                        => ['sometimes', 'required', 'string', 'max:255'],
            'description'                 => ['nullable', 'string'],
            'cost_component_structure_id' => ['nullable', 'integer'],
            'is_active'                   => ['nullable', 'boolean'],
        ]);

        $sheet->update($validated);

        return $this->success($sheet->refresh());
    }

    /**
     * Soft-delete a costing sheet.
     */
    public function destroy(int $id): JsonResponse
    {
        $sheet = CostingSheet::findOrFail($id);
        $sheet->delete();

        return $this->success(['message' => 'Costing sheet deleted.']);
    }

    // ================================================================
    // Rows
    // ================================================================

    /**
     * List rows for a costing sheet.
     */
    public function rows(int $id): JsonResponse
    {
        $sheet = CostingSheet::findOrFail($id);

        $rows = $sheet->rows()->with([
            'overheadKey:id,code,name,overhead_type',
            'baseCostElement:id,code,name',
            'creditCostCenter:id,code,name',
            'creditCostElement:id,code,name',
        ])->get();

        return $this->success($rows);
    }

    /**
     * Add a row to a costing sheet.
     */
    public function addRow(Request $request, int $id): JsonResponse
    {
        $sheet = CostingSheet::findOrFail($id);

        $validated = $request->validate([
            'row_type'               => ['required', Rule::in(['base', 'overhead', 'credit'])],
            'description'            => ['required', 'string', 'max:255'],
            'sort_order'             => ['nullable', 'integer', 'min:0'],
            'base_cost_element_id'   => ['nullable', 'integer', 'exists:cost_elements,id'],
            'overhead_key_id'        => ['nullable', 'integer', 'exists:overhead_keys,id'],
            'credit_cost_center_id'  => ['nullable', 'integer', 'exists:cost_centers,id'],
            'credit_cost_element_id' => ['nullable', 'integer', 'exists:cost_elements,id'],
            'from_row'               => ['nullable', 'integer', 'min:0'],
            'to_row'                 => ['nullable', 'integer', 'min:0', 'gte:from_row'],
        ]);

        $row = $this->service->addRow($sheet, $validated);

        return $this->created($row->load([
            'overheadKey:id,code,name',
            'baseCostElement:id,code,name',
        ]));
    }

    // ================================================================
    // Run
    // ================================================================

    /**
     * Execute the overhead calculation for a cost object.
     *
     * POST /costing-sheets/{id}/run
     */
    public function run(Request $request, int $id): JsonResponse
    {
        $sheet = CostingSheet::findOrFail($id);

        $validated = $request->validate([
            'reference_type' => ['required', 'string', 'max:50'],
            'reference_id'   => ['required', 'integer', 'min:1'],
        ]);

        $run = $this->service->calculateOverhead(
            $validated['reference_type'],
            $validated['reference_id'],
            $sheet->id
        );

        return $this->success($run->load('results.row:id,description,row_type,sort_order'));
    }
}
