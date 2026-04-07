<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProductionVersion;
use App\Services\Manufacturing\ProductionVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionVersionController extends Controller
{
    public function __construct(
        private readonly ProductionVersionService $service,
    ) {}

    /**
     * List production versions with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductionVersion::with(['product', 'bom', 'routing'])
            ->when($request->product_id, fn($q, $id) => $q->forProduct((int) $id))
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderByDesc('is_default')
            ->orderBy('product_id')
            ->orderBy('version_code');

        $versions = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($versions);
    }

    /**
     * Create a new production version.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'       => ['required', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'version_code'     => 'required|string|max:20',
            'description'      => 'nullable|string|max:255',
            'bom_id'           => ['nullable', Rule::exists('bom_templates', 'id')->where('organization_id', auth()->user()->organization_id)],
            'routing_id'       => ['nullable', Rule::exists('routing_headers', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lot_size_from'    => 'nullable|numeric|min:0',
            'lot_size_to'      => 'nullable|numeric|min:0|gte:lot_size_from',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'production_plant' => 'nullable|string|max:50',
            'is_default'       => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
        ]);

        $version = $this->service->create($validated);

        return $this->created($version->load(['product', 'bom', 'routing']));
    }

    /**
     * Show a single production version.
     */
    public function show(int $id): JsonResponse
    {
        $version = ProductionVersion::with(['product', 'bom', 'routing'])->find($id);

        if ($version === null) {
            return $this->notFound('Production version not found.');
        }

        return $this->success($version);
    }

    /**
     * Update a production version.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $version = ProductionVersion::find($id);

        if ($version === null) {
            return $this->notFound('Production version not found.');
        }

        $validated = $request->validate([
            'version_code'     => 'sometimes|string|max:20',
            'description'      => 'nullable|string|max:255',
            'bom_id'           => ['nullable', Rule::exists('bom_templates', 'id')->where('organization_id', auth()->user()->organization_id)],
            'routing_id'       => ['nullable', Rule::exists('routing_headers', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lot_size_from'    => 'nullable|numeric|min:0',
            'lot_size_to'      => 'nullable|numeric|min:0',
            'valid_from'       => 'sometimes|date',
            'valid_to'         => 'nullable|date',
            'production_plant' => 'nullable|string|max:50',
            'is_default'       => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
        ]);

        $updated = $this->service->update($version, $validated);

        return $this->success($updated->load(['product', 'bom', 'routing']));
    }

    /**
     * Soft-delete a production version.
     */
    public function destroy(int $id): JsonResponse
    {
        $version = ProductionVersion::find($id);

        if ($version === null) {
            return $this->notFound('Production version not found.');
        }

        $version->delete();

        return $this->success(null, 'Production version deleted.');
    }

    /**
     * Set a version as the default for its product.
     */
    public function setDefault(int $id): JsonResponse
    {
        $version = ProductionVersion::find($id);

        if ($version === null) {
            return $this->notFound('Production version not found.');
        }

        $this->service->setDefault($version);

        return $this->success($version->fresh(['product', 'bom', 'routing']), 'Default version updated.');
    }

    /**
     * List all versions for a specific product.
     */
    public function forProduct(int $productId): JsonResponse
    {
        $versions = $this->service->getVersionsForProduct($productId);

        return $this->success($versions);
    }
}
