<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\OverheadKey;
use App\Services\Accounting\CostingSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OverheadKeyController extends Controller
{
    public function __construct(
        private readonly CostingSheetService $service
    ) {}

    // ================================================================
    // Overhead Keys
    // ================================================================

    /**
     * List overhead keys.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OverheadKey::orderBy('code')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q) use ($search): void {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('overhead_type'), fn($q) => $q->where('overhead_type', $request->overhead_type));

        $perPage = $request->integer('per_page', 20);

        return $this->paginated($query->paginate($perPage));
    }

    /**
     * Create a new overhead key.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'          => ['required', 'string', 'max:30'],
            'name'          => ['required', 'string', 'max:255'],
            'overhead_type' => ['required', Rule::in(OverheadKey::TYPES)],
        ]);

        $key = $this->service->createOverheadKey(
            array_merge($validated, ['organization_id' => $orgId])
        );

        return $this->created($key);
    }

    /**
     * Show a single overhead key with its rates.
     */
    public function show(int $id): JsonResponse
    {
        $key = OverheadKey::with([
            'rates.costCenter:id,code,name',
            'rates.activityType:id,code,name',
        ])->findOrFail($id);

        return $this->success($key);
    }

    /**
     * Update an overhead key.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $key = OverheadKey::findOrFail($id);

        $validated = $request->validate([
            'code'          => ['sometimes', 'required', 'string', 'max:30'],
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'overhead_type' => ['sometimes', 'required', Rule::in(OverheadKey::TYPES)],
        ]);

        $key->update($validated);

        return $this->success($key->refresh());
    }

    /**
     * Soft-delete an overhead key.
     */
    public function destroy(int $id): JsonResponse
    {
        $key = OverheadKey::findOrFail($id);
        $key->delete();

        return $this->success(['message' => 'Overhead key deleted.']);
    }

    // ================================================================
    // Rates
    // ================================================================

    /**
     * List rates for an overhead key.
     */
    public function rates(int $id): JsonResponse
    {
        $key = OverheadKey::findOrFail($id);

        $rates = $key->rates()->with([
            'costCenter:id,code,name',
            'activityType:id,code,name',
        ])->get();

        return $this->success($rates);
    }

    /**
     * Add a rate to an overhead key.
     *
     * POST /overhead-keys/{id}/rates
     */
    public function addRate(Request $request, int $id): JsonResponse
    {
        $key = OverheadKey::findOrFail($id);

        $validated = $request->validate([
            'validity_from'    => ['required', 'date'],
            'validity_to'      => ['nullable', 'date', 'after_or_equal:validity_from'],
            'overhead_rate'    => ['required', 'numeric', 'min:0'],
            'currency_code'    => ['required', 'string', 'size:3'],
            'cost_center_id'   => ['nullable', 'integer', 'exists:cost_centers,id'],
            'activity_type_id' => ['nullable', 'integer', 'exists:activity_types,id'],
        ]);

        $rate = $this->service->addRate($key, $validated);

        return $this->created($rate->load([
            'costCenter:id,code,name',
            'activityType:id,code,name',
        ]));
    }
}
