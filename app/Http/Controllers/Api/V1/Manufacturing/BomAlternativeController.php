<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\BomAlternative;
use App\Services\Manufacturing\BomAlternativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BomAlternativeController extends Controller
{
    public function __construct(
        private readonly BomAlternativeService $service
    ) {}

    public function index(int $productId, Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'usage_type', 'valid_on']);
        $alternatives = $this->service->list($productId, $filters);

        return $this->success($alternatives, 'BOM alternatives retrieved successfully.');
    }

    public function store(int $productId, Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'alternative_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('bom_alternatives')
                    ->where('organization_id', $orgId)
                    ->where('product_id', $productId),
            ],
            'alternative_name' => 'nullable|string|max:100',
            'bom_template_id' => ['nullable', Rule::exists('bom_templates', 'id')->where('organization_id', $orgId)],
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'is_default' => 'boolean',
            'usage_type' => 'nullable|in:production,engineering,costing,plant_maintenance',
            'lot_size_from' => 'nullable|numeric|min:0',
            'lot_size_to' => 'nullable|numeric|min:0|gte:lot_size_from',
            'status' => 'nullable|in:active,inactive,obsolete',
            'notes' => 'nullable|string',
        ]);

        $validated['product_id'] = $productId;

        $alternative = $this->service->create($validated);

        return $this->created($alternative, 'BOM alternative created successfully.');
    }

    public function show(int $productId, int $id): JsonResponse
    {
        $alternative = BomAlternative::with(['product', 'bomTemplate'])
            ->forProduct($productId)
            ->findOrFail($id);

        return $this->success($alternative);
    }

    public function update(int $productId, int $id, Request $request): JsonResponse
    {
        $alternative = BomAlternative::forProduct($productId)->findOrFail($id);
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'alternative_name' => 'nullable|string|max:100',
            'bom_template_id' => ['nullable', Rule::exists('bom_templates', 'id')->where('organization_id', $orgId)],
            'valid_from' => 'sometimes|required|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'is_default' => 'boolean',
            'usage_type' => 'nullable|in:production,engineering,costing,plant_maintenance',
            'lot_size_from' => 'nullable|numeric|min:0',
            'lot_size_to' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive,obsolete',
            'notes' => 'nullable|string',
        ]);

        $updated = $this->service->update($alternative, $validated);

        return $this->success($updated, 'BOM alternative updated successfully.');
    }

    public function destroy(int $productId, int $id): JsonResponse
    {
        $alternative = BomAlternative::forProduct($productId)->findOrFail($id);
        $alternative->delete();

        return $this->noContent();
    }

    public function setDefault(int $productId, int $id): JsonResponse
    {
        $alternative = BomAlternative::forProduct($productId)->findOrFail($id);
        $this->service->setDefault($alternative);

        return $this->success(null, 'Default BOM alternative set successfully.');
    }

    public function determine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|numeric|min:0.0001',
            'date' => 'nullable|date',
            'usage_type' => 'nullable|in:production,engineering,costing,plant_maintenance',
        ]);

        $alternative = $this->service->determineAlternative(
            productId: (int) $validated['product_id'],
            quantity: (float) $validated['quantity'],
            date: $validated['date'] ?? null,
            usageType: $validated['usage_type'] ?? 'production',
        );

        if ($alternative === null) {
            return $this->error('No suitable BOM alternative found for the given parameters.', 'NOT_FOUND', 404);
        }

        return $this->success($alternative->load(['product', 'bomTemplate']), 'BOM alternative determined.');
    }
}
