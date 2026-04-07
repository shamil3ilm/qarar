<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Manufacturing\BomTemplateResource;
use App\Models\Manufacturing\BomTemplate;
use App\Services\Manufacturing\BomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BomController extends Controller
{
    public function __construct(
        private BomService $bomService
    ) {
    }

    /**
     * List BOM templates with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BomTemplate::with(['product', 'outputUnit', 'defaultWarehouse'])
            ->withCount(['lines', 'operations', 'workOrders'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->product_id, fn($q, $id) => $q->forProduct($id))
            ->when($request->effective === 'true', fn($q) => $q->effective())
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('bom_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'created_at', 'updated_at', 'status'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        $boms = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($boms, BomTemplateResource::class);
    }

    /**
     * Store a new BOM template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bom_number' => 'nullable|string|max:50',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'variant_id' => ['nullable', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'output_quantity' => 'required|numeric|min:0.0001',
            'output_unit_id' => 'nullable|exists:units_of_measure,id',
            'default_warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_labor_cost' => 'nullable|numeric|min:0',
            'overhead_cost' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.variant_id' => ['nullable', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_id' => 'nullable|exists:units_of_measure,id',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
            'lines.*.wastage_percentage' => 'nullable|numeric|min:0|max:100',
            'lines.*.is_critical' => 'nullable|boolean',
            'lines.*.warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'operations' => 'nullable|array',
            'operations.*.name' => 'required|string|max:100',
            'operations.*.description' => 'nullable|string',
            'operations.*.instructions' => 'nullable|string',
            'operations.*.estimated_minutes' => 'nullable|integer|min:0',
            'operations.*.labor_cost_per_hour' => 'nullable|numeric|min:0',
            'operations.*.workstation' => 'nullable|string|max:100',
            'operations.*.required_skills' => 'nullable|array',
            'operations.*.is_subcontracted' => 'nullable|boolean',
        ]);

        try {
            $bom = $this->bomService->create(
                collect($validated)->except(['lines', 'operations'])->toArray(),
                $validated['lines'],
                $validated['operations'] ?? [],
                auth()->id()
            );
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new BomTemplateResource($bom), 'BOM template created successfully.');
    }

    /**
     * Show a specific BOM template.
     */
    public function show(BomTemplate $bom): JsonResponse
    {
        return $this->success(new BomTemplateResource(
            $bom->load(['product', 'variant', 'outputUnit', 'defaultWarehouse', 'lines.product', 'lines.unit', 'operations', 'createdBy'])
        ));
    }

    /**
     * Update a BOM template.
     */
    public function update(Request $request, BomTemplate $bom): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'output_quantity' => 'sometimes|numeric|min:0.0001',
            'output_unit_id' => 'nullable|exists:units_of_measure,id',
            'default_warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_labor_cost' => 'nullable|numeric|min:0',
            'overhead_cost' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string',
            'lines' => 'sometimes|array|min:1',
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.variant_id' => ['nullable', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_id' => 'nullable|exists:units_of_measure,id',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
            'lines.*.wastage_percentage' => 'nullable|numeric|min:0|max:100',
            'lines.*.is_critical' => 'nullable|boolean',
            'lines.*.warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'operations' => 'sometimes|array',
            'operations.*.name' => 'required|string|max:100',
            'operations.*.description' => 'nullable|string',
            'operations.*.instructions' => 'nullable|string',
            'operations.*.estimated_minutes' => 'nullable|integer|min:0',
            'operations.*.labor_cost_per_hour' => 'nullable|numeric|min:0',
            'operations.*.workstation' => 'nullable|string|max:100',
            'operations.*.required_skills' => 'nullable|array',
            'operations.*.is_subcontracted' => 'nullable|boolean',
        ]);

        return $this->tryAction(
            fn() => new BomTemplateResource($this->bomService->update(
                $bom,
                collect($validated)->except(['lines', 'operations'])->toArray(),
                $validated['lines'] ?? null,
                $validated['operations'] ?? null
            )),
            'BOM template updated successfully.'
        );
    }

    /**
     * Delete a draft BOM template.
     */
    public function destroy(BomTemplate $bom): JsonResponse
    {
        if (!$bom->isDraft()) {
            return $this->error('Only draft BOM templates can be deleted.', 'VALIDATION_ERROR', 422);
        }

        // Check if used in work orders
        if ($bom->workOrders()->exists()) {
            return $this->error('BOM template cannot be deleted. It has associated work orders.', 'VALIDATION_ERROR', 422);
        }

        $bom->lines()->delete();
        $bom->operations()->delete();
        $bom->delete();

        return $this->success(null, 'BOM template deleted successfully.');
    }

    /**
     * Activate or deactivate a BOM template.
     * PATCH /bom-templates/{bom}/active  {"active": true|false}
     */
    public function setActive(Request $request, BomTemplate $bom): JsonResponse
    {
        $activate = $request->boolean('active');

        return $this->tryAction(
            fn() => new BomTemplateResource(
                $activate ? $this->bomService->activate($bom) : $this->bomService->deactivate($bom)
            ),
            $activate ? 'BOM template activated successfully.' : 'BOM template deactivated successfully.',
        );
    }

    /**
     * Duplicate a BOM template.
     */
    public function duplicate(Request $request, BomTemplate $bom): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:200',
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
        ]);

        try {
            $newBom = $this->bomService->duplicate($bom, $validated, auth()->id());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new BomTemplateResource($newBom), 'BOM template duplicated successfully.');
    }

    /**
     * Get cost breakdown for a BOM template.
     */
    public function costBreakdown(Request $request, BomTemplate $bom): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'nullable|numeric|min:0.0001',
        ]);

        $quantity = (float) ($validated['quantity'] ?? $bom->output_quantity);
        $breakdown = $this->bomService->getCostBreakdown($bom, $quantity);

        return $this->success($breakdown);
    }

    /**
     * Check material availability for production.
     */
    public function checkAvailability(Request $request, BomTemplate $bom): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
        ]);

        $availability = $this->bomService->checkAvailability(
            $bom,
            (float) $validated['quantity'],
            $validated['warehouse_id'] ?? null
        );

        return $this->success($availability);
    }

    /**
     * Get BOMs for a specific product.
     */
    public function forProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'active_only' => 'nullable|boolean',
        ]);

        $boms = $this->bomService->getForProduct(
            (int) $validated['product_id'],
            $validated['active_only'] ?? true
        );

        return $this->success(BomTemplateResource::collection($boms));
    }
}
