<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\HazmatClassification;
use App\Models\Inventory\HazmatStorageClass;
use App\Models\Inventory\HazmatTransportRegulation;
use App\Models\Inventory\SafetyDataSheet;
use App\Services\Inventory\HazmatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HazmatController extends Controller
{
    public function __construct(
        private HazmatService $service,
    ) {}

    /**
     * List hazmat classifications for the organization.
     */
    public function classifications(Request $request): JsonResponse
    {
        $query = HazmatClassification::query()
            ->when($request->system, fn($q, $s) => $q->bySystem($s))
            ->when($request->active_only, fn($q) => $q->active())
            ->orderBy('classification_system')
            ->orderBy('code');

        $classifications = $query->paginate($request->integer('per_page', 50));

        return $this->paginated($classifications);
    }

    /**
     * Create a new hazmat classification.
     */
    public function storeClassification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classification_system' => ['required', Rule::in(['ghs', 'un', 'adr', 'iata'])],
            'code'                  => ['required', 'string', 'max:20'],
            'name'                  => ['required', 'string', 'max:255'],
            'hazard_class'          => ['required', 'string', 'max:50'],
            'packing_group'         => ['nullable', Rule::in(['I', 'II', 'III'])],
            'signal_word'           => ['nullable', Rule::in(['danger', 'warning'])],
            'is_active'             => ['boolean'],
        ]);

        $classification = HazmatClassification::create($validated);

        return $this->created($classification);
    }

    /**
     * List hazmat storage classes.
     */
    public function storageClasses(Request $request): JsonResponse
    {
        $query = HazmatStorageClass::query()
            ->orderBy('code');

        $classes = $query->paginate($request->integer('per_page', 50));

        return $this->paginated($classes);
    }

    /**
     * Create a new hazmat storage class.
     */
    public function storeStorageClass(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'                  => ['required', 'string', 'max:10'],
            'name'                  => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'max_quantity_kg'       => ['nullable', 'numeric', 'min:0'],
            'requires_ventilation'  => ['boolean'],
            'requires_grounding'    => ['boolean'],
            'fire_resistance_class' => ['nullable', 'string', 'max:10'],
        ]);

        $storageClass = HazmatStorageClass::create($validated);

        return $this->created($storageClass);
    }

    /**
     * Check storage compatibility between two storage classes.
     * POST body: { storage_class_a_id, storage_class_b_id }
     */
    public function compatibilityCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'storage_class_a_id' => ['required', 'exists:hazmat_storage_classes,id'],
            'storage_class_b_id' => ['required', 'exists:hazmat_storage_classes,id'],
        ]);

        $compatible = $this->service->checkStorageCompatibility(
            (int) $validated['storage_class_a_id'],
            (int) $validated['storage_class_b_id']
        );

        return $this->success([
            'storage_class_a_id' => $validated['storage_class_a_id'],
            'storage_class_b_id' => $validated['storage_class_b_id'],
            'is_compatible'      => $compatible,
        ]);
    }

    /**
     * List Safety Data Sheets with optional product filter.
     */
    public function sdsIndex(Request $request): JsonResponse
    {
        $query = SafetyDataSheet::with(['product'])
            ->when($request->product_id, fn($q, $id) => $q->forProduct((int) $id))
            ->when($request->language, fn($q, $lang) => $q->forLanguage($lang))
            ->when($request->current_only, fn($q) => $q->current())
            ->orderByDesc('revision_date');

        $sheets = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($sheets);
    }

    /**
     * Show a single Safety Data Sheet with its sections.
     */
    public function sdsShow(int $id): JsonResponse
    {
        $sds = SafetyDataSheet::with('sections')->findOrFail($id);

        return $this->success($sds);
    }

    /**
     * Create a new Safety Data Sheet.
     */
    public function sdsStore(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $validated = $request->validate([
            'product_id'     => [
                'required',
                Rule::exists('products', 'id')->where('organization_id', $organizationId),
            ],
            'sds_number'     => ['required', 'string', 'max:50'],
            'version'        => ['required', 'string', 'max:20'],
            'revision_date'  => ['required', 'date'],
            'language_code'  => ['nullable', 'string', 'max:5'],
            'supplier_name'  => ['nullable', 'string', 'max:150'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'is_current'     => ['boolean'],
            'sections'       => ['nullable', 'array'],
            'sections.*.section_number' => ['required', 'integer', 'min:1', 'max:16'],
            'sections.*.section_title'  => ['required', 'string', 'max:100'],
            'sections.*.content'        => ['required', 'string'],
        ]);

        $validated['organization_id'] = $organizationId;

        $sds = $this->service->createSds($validated);

        return $this->created($sds);
    }

    /**
     * Return the current SDS for a product in the requested language.
     */
    public function sdsCurrentForProduct(int $productId, Request $request): JsonResponse
    {
        $language = $request->string('language', 'en')->value();
        $sds = $this->service->getCurrentSds($productId, $language);

        if ($sds === null) {
            return $this->notFound('No current SDS found for this product.');
        }

        return $this->success($sds);
    }

    /**
     * List transport regulations for a specific product.
     */
    public function transportRegulations(int $productId, Request $request): JsonResponse
    {
        $mode = $request->string('mode')->value() ?: null;

        $regulations = HazmatTransportRegulation::forProduct($productId)
            ->when($mode, fn($q, $m) => $q->forMode($m))
            ->get();

        return $this->success($regulations);
    }

    /**
     * Create a transport regulation record.
     */
    public function storeTransportRegulation(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $validated = $request->validate([
            'product_id'           => [
                'required',
                Rule::exists('products', 'id')->where('organization_id', $organizationId),
            ],
            'un_number'            => ['nullable', 'string', 'max:10'],
            'proper_shipping_name' => ['nullable', 'string', 'max:200'],
            'hazard_class'         => ['nullable', 'string', 'max:20'],
            'packing_group'        => ['nullable', Rule::in(['I', 'II', 'III'])],
            'transport_mode'       => ['required', Rule::in(['road', 'air', 'sea', 'rail'])],
            'is_forbidden'         => ['boolean'],
            'special_provisions'   => ['nullable', 'string'],
        ]);

        $validated['organization_id'] = $organizationId;

        $regulation = HazmatTransportRegulation::create($validated);

        return $this->created($regulation);
    }

    /**
     * Assign hazmat classifications to a product.
     */
    public function classifyProduct(Request $request, int $productId): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $request->validate([
            'classifications'                           => ['required', 'array', 'min:1'],
            'classifications.*.hazmat_classification_id' => [
                'required',
                Rule::exists('hazmat_classifications', 'id')->where('organization_id', $organizationId),
            ],
            'classifications.*.storage_class_id'       => [
                'nullable',
                Rule::exists('hazmat_storage_classes', 'id')->where('organization_id', $organizationId),
            ],
            'classifications.*.is_primary'             => ['boolean'],
        ]);

        $this->service->classifyProduct($productId, $request->input('classifications'));

        return $this->success(['message' => 'Product classifications updated successfully.']);
    }

    /**
     * List all hazardous products in the organization.
     */
    public function hazardousProducts(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;
        $products = $this->service->getHazardousProducts($organizationId);

        return $this->success($products);
    }
}
