<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customs;

use App\Http\Controllers\Controller;
use App\Models\Customs\ExciseCategory;
use App\Services\Customs\ExciseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExciseCategoryController extends Controller
{
    public function __construct(
        private ExciseService $exciseService
    ) {
    }

    /**
     * Resolve an excise category by ID with organization scoping.
     */
    private function resolveCategory(int $id): ?ExciseCategory
    {
        return ExciseCategory::find($id);
    }

    /**
     * List excise categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExciseCategory::with(['rates' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->when($request->boolean('active_only', true), fn($q) => $q->active())
            ->when($request->has('country_code'), fn($q) => $q->forCountry($request->input('country_code')))
            ->when($request->has('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            });

        $categories = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($categories);
    }

    /**
     * Show a single excise category.
     */
    public function show(int $category): JsonResponse
    {
        $exciseCategory = $this->resolveCategory($category);

        if (!$exciseCategory) {
            return $this->notFound('Excise category not found');
        }

        $exciseCategory->load(['rates', 'productMappings.product:id,name,sku']);

        return $this->success($exciseCategory);
    }

    /**
     * Create an excise category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'country_code' => ['nullable', 'string', 'max:3'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        try {
            $category = $this->exciseService->createCategory($validated);
            return $this->created($category);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Update an excise category.
     */
    public function update(Request $request, int $category): JsonResponse
    {
        $exciseCategory = $this->resolveCategory($category);

        if (!$exciseCategory) {
            return $this->notFound('Excise category not found');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'country_code' => ['nullable', 'string', 'max:3'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $exciseCategory->update($validated);

        return $this->success($exciseCategory->fresh(), 'Excise category updated successfully');
    }

    /**
     * Delete an excise category.
     */
    public function destroy(int $category): JsonResponse
    {
        $exciseCategory = $this->resolveCategory($category);

        if (!$exciseCategory) {
            return $this->notFound('Excise category not found');
        }

        if ($exciseCategory->productMappings()->count() > 0) {
            return $this->error('Cannot delete category with product mappings.', 'HAS_DEPENDENCIES', 400);
        }

        $exciseCategory->rates()->delete();
        $exciseCategory->delete();

        return $this->success(null, 'Excise category deleted successfully');
    }

    /**
     * Add a rate to a category.
     */
    public function addRate(Request $request, int $category): JsonResponse
    {
        $exciseCategory = $this->resolveCategory($category);

        if (!$exciseCategory) {
            return $this->notFound('Excise category not found');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate_type' => ['required', 'in:percentage,specific,composite'],
            'rate_percent' => ['nullable', 'numeric', 'min:0'],
            'specific_amount' => ['nullable', 'numeric', 'min:0'],
            'specific_unit' => ['nullable', 'string', 'max:20'],
            'currency_code' => ['nullable', 'string', 'max:3'],
            'country_code' => ['nullable', 'string', 'max:3'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['excise_category_id'] = $exciseCategory->id;

        try {
            $rate = $this->exciseService->createRate($validated);
            return $this->created($rate);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Map a product to this excise category.
     */
    public function mapProduct(Request $request, int $category): JsonResponse
    {
        $exciseCategory = $this->resolveCategory($category);

        if (!$exciseCategory) {
            return $this->notFound('Excise category not found');
        }

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'excise_rate_id' => ['nullable', 'exists:excise_rates,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['excise_category_id'] = $exciseCategory->id;

        $mapping = $this->exciseService->mapProduct($validated);

        return $this->created($mapping);
    }
}
