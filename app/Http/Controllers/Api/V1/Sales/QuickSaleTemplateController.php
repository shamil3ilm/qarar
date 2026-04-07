<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\QuickSaleTemplate;
use App\Services\Sales\QuickSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickSaleTemplateController extends Controller
{
    public function __construct(
        private QuickSaleService $quickSaleService
    ) {}

    /**
     * List quick sale templates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = QuickSaleTemplate::with(['defaultCustomer'])
            ->latest()
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('search'), fn($q) => $q->search($request->input('search')));

        $templates = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($templates);
    }

    /**
     * Create a new template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'default_items' => 'nullable|array',
            'default_items.*.product_id' => 'nullable|integer|exists:products,id',
            'default_items.*.description' => 'required|string|max:500',
            'default_items.*.quantity' => 'required|numeric|gt:0',
            'default_items.*.unit_price' => 'required|numeric|min:0',
            'default_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'default_customer_id' => 'nullable|integer|exists:contacts,id',
            'default_payment_method' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $template = $this->quickSaleService->createTemplate($validated);

        return $this->created($template, 'Quick sale template created successfully.');
    }

    /**
     * Show a template.
     */
    public function show(QuickSaleTemplate $quickSaleTemplate): JsonResponse
    {
        $quickSaleTemplate->load(['defaultCustomer']);

        return $this->success($quickSaleTemplate);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, QuickSaleTemplate $quickSaleTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'default_items' => 'nullable|array',
            'default_items.*.product_id' => 'nullable|integer|exists:products,id',
            'default_items.*.description' => 'required|string|max:500',
            'default_items.*.quantity' => 'required|numeric|gt:0',
            'default_items.*.unit_price' => 'required|numeric|min:0',
            'default_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'default_customer_id' => 'nullable|integer|exists:contacts,id',
            'default_payment_method' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $quickSaleTemplate->update($validated);

        return $this->success($quickSaleTemplate->fresh(), 'Quick sale template updated successfully.');
    }

    /**
     * Delete a template.
     */
    public function destroy(QuickSaleTemplate $quickSaleTemplate): JsonResponse
    {
        $quickSaleTemplate->delete();

        return $this->success(null, 'Quick sale template deleted successfully.');
    }

    /**
     * Use a template (get pre-populated data).
     */
    public function use(QuickSaleTemplate $quickSaleTemplate): JsonResponse
    {
        $data = $this->quickSaleService->useTemplate($quickSaleTemplate);

        return $this->success($data);
    }

    /**
     * Duplicate a template.
     */
    public function duplicate(Request $request, QuickSaleTemplate $quickSaleTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $newTemplate = $this->quickSaleService->duplicateTemplate(
            $quickSaleTemplate,
            $validated['name'] ?? null
        );

        return $this->created($newTemplate, 'Template duplicated successfully.');
    }
}
