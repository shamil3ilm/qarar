<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StorageType;
use App\Models\Inventory\StorageTypeDeterminationRule;
use App\Services\Inventory\StorageTypeDeterminationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageTypeController extends Controller
{
    public function __construct(
        private StorageTypeDeterminationService $service
    ) {}

    // -------------------------------------------------------------------------
    // Storage Types CRUD
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $types = $this->service->list([
            ...$request->only(['warehouse_id', 'is_active', 'storage_class', 'per_page']),
        ]);

        return $this->paginated($types);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'               => ['required', 'exists:warehouses,id'],
            'storage_type_code'          => ['required', 'string', 'max:20'],
            'storage_type_name'          => ['required', 'string', 'max:100'],
            'storage_class'              => ['required', 'in:bulk,rack,floor,refrigerated,hazmat,high_security,quarantine'],
            'capacity_management'        => ['sometimes', 'in:no_check,total_weight,total_qty,occupied_bins'],
            'max_weight'                 => ['nullable', 'numeric', 'min:0'],
            'max_quantity'               => ['nullable', 'numeric', 'min:0'],
            'total_bins'                 => ['nullable', 'integer', 'min:0'],
            'current_utilization_percent'=> ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active'                  => ['sometimes', 'boolean'],
        ]);

        $type = $this->service->createType([
            ...$validated,
            'organization_id' => $this->organizationId($request),
        ]);

        return $this->created($type, 'Storage type created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $type = StorageType::with(['warehouse:id,name', 'determinationRules'])
            ->findOrFail($id);

        return $this->success($type);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $type = StorageType::findOrFail($id);

        $validated = $request->validate([
            'storage_type_name'           => ['sometimes', 'string', 'max:100'],
            'storage_class'               => ['sometimes', 'in:bulk,rack,floor,refrigerated,hazmat,high_security,quarantine'],
            'capacity_management'         => ['sometimes', 'in:no_check,total_weight,total_qty,occupied_bins'],
            'max_weight'                  => ['nullable', 'numeric', 'min:0'],
            'max_quantity'                => ['nullable', 'numeric', 'min:0'],
            'total_bins'                  => ['nullable', 'integer', 'min:0'],
            'current_utilization_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active'                   => ['sometimes', 'boolean'],
        ]);

        $type = $this->service->updateType($type, $validated);

        return $this->success($type, 'Storage type updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $type = StorageType::findOrFail($id);
        $type->delete();

        return $this->success(null, 'Storage type deleted successfully.');
    }

    // -------------------------------------------------------------------------
    // Determination Rules
    // -------------------------------------------------------------------------

    public function addRule(Request $request, int $id): JsonResponse
    {
        $storageType = StorageType::findOrFail($id);

        $validated = $request->validate([
            'warehouse_id'         => ['sometimes', 'exists:warehouses,id'],
            'movement_type'        => ['required', 'in:goods_receipt,goods_issue,transfer,returns'],
            'product_storage_class'=> ['nullable', 'string', 'max:50'],
            'max_weight_kg'        => ['nullable', 'numeric', 'min:0'],
            'priority'             => ['sometimes', 'integer', 'min:1', 'max:255'],
            'is_active'            => ['sometimes', 'boolean'],
        ]);

        $rule = $this->service->addRule($storageType, $validated);

        return $this->created($rule, 'Determination rule added successfully.');
    }

    public function updateRule(Request $request, int $id, int $ruleId): JsonResponse
    {
        StorageType::findOrFail($id);
        $rule = StorageTypeDeterminationRule::where('storage_type_id', $id)
            ->findOrFail($ruleId);

        $validated = $request->validate([
            'movement_type'         => ['sometimes', 'in:goods_receipt,goods_issue,transfer,returns'],
            'product_storage_class' => ['nullable', 'string', 'max:50'],
            'max_weight_kg'         => ['nullable', 'numeric', 'min:0'],
            'priority'              => ['sometimes', 'integer', 'min:1', 'max:255'],
            'is_active'             => ['sometimes', 'boolean'],
        ]);

        $rule = $this->service->updateRule($rule, $validated);

        return $this->success($rule, 'Determination rule updated successfully.');
    }

    public function removeRule(int $id, int $ruleId): JsonResponse
    {
        StorageType::findOrFail($id);
        $rule = StorageTypeDeterminationRule::where('storage_type_id', $id)
            ->findOrFail($ruleId);

        $rule->delete();

        return $this->success(null, 'Determination rule removed successfully.');
    }

    // -------------------------------------------------------------------------
    // Storage Type Determination
    // -------------------------------------------------------------------------

    /**
     * Determine the best storage type for a putaway scenario.
     */
    public function determine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'          => ['required', 'exists:warehouses,id'],
            'movement_type'         => ['required', 'in:goods_receipt,goods_issue,transfer,returns'],
            'product_storage_class' => ['nullable', 'string', 'max:50'],
            'weight'                => ['nullable', 'numeric', 'min:0'],
        ]);

        $storageType = $this->service->determineStorageType(
            (int) $validated['warehouse_id'],
            $validated['movement_type'],
            $validated['product_storage_class'] ?? null,
            isset($validated['weight']) ? (float) $validated['weight'] : null
        );

        if ($storageType === null) {
            return $this->success(null, 'No suitable storage type found for the given criteria.');
        }

        return $this->success($storageType, 'Storage type determined successfully.');
    }
}
