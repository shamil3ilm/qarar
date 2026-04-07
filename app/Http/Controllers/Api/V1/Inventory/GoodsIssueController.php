<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\GoodsIssue;
use App\Services\Inventory\GoodsIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoodsIssueController extends Controller
{
    public function __construct(
        private GoodsIssueService $goodsIssueService
    ) {}

    /**
     * List Goods Issues for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GoodsIssue::with(['warehouse', 'lines.product', 'creator', 'postedBy'])
            ->latest('gi_date')
            ->when($request->filled('warehouse_id'), fn($q) => $q->inWarehouse($request->integer('warehouse_id')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('movement_type'), fn($q) => $q->byMovementType($request->input('movement_type')))
            ->when($request->filled('from_date'), fn($q) => $q->where('gi_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn($q) => $q->where('gi_date', '<=', $request->input('to_date')));

        $perPage = $request->integer('per_page', 15);
        $goodsIssues = $query->paginate($perPage);

        return $this->paginated($goodsIssues, \App\Http\Resources\Inventory\GoodsIssueResource::class);
    }

    /**
     * Create a new Goods Issue in draft status.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'gi_date'          => 'required|date',
            'movement_type'    => ['required', Rule::in([
                GoodsIssue::MOVEMENT_SALES_DELIVERY,
                GoodsIssue::MOVEMENT_PRODUCTION_ISSUE,
                GoodsIssue::MOVEMENT_SCRAPPING,
                GoodsIssue::MOVEMENT_TRANSFER,
                GoodsIssue::MOVEMENT_OTHER,
            ])],
            'warehouse_id'     => ['required', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'branch_id'        => ['nullable', 'integer', Rule::exists('branches', 'id')->where('organization_id', $orgId)],
            'reference_type'   => 'nullable|string|max:100',
            'reference_id'     => 'nullable|integer',
            'notes'            => 'nullable|string|max:2000',
            'lines'            => 'required|array|min:1',
            'lines.*.product_id'    => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'lines.*.variant_id'    => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'lines.*.warehouse_id'  => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'lines.*.location_id'   => 'nullable|integer|exists:warehouse_locations,id',
            'lines.*.batch_id'      => 'nullable|integer|exists:inventory_batches,id',
            'lines.*.unit_id'       => 'nullable|integer|exists:units_of_measure,id',
            'lines.*.quantity'      => 'required|numeric|min:0.0001',
            'lines.*.unit_cost'     => 'nullable|numeric|min:0',
            'lines.*.serial_number' => 'nullable|string|max:100',
            'lines.*.notes'         => 'nullable|string|max:255',
        ]);

        try {
            $gi = $this->goodsIssueService->create($validated, auth()->id());

            return $this->created(
                new \App\Http\Resources\Inventory\GoodsIssueResource($gi),
                'Goods Issue created successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a single Goods Issue.
     */
    public function show(GoodsIssue $goodsIssue): JsonResponse
    {
        $goodsIssue->load([
            'lines.product',
            'lines.variant',
            'lines.unit',
            'lines.warehouse',
            'lines.location',
            'warehouse',
            'branch',
            'journalEntry',
            'creator',
            'postedBy',
            'reversedBy',
        ]);

        return $this->success(new \App\Http\Resources\Inventory\GoodsIssueResource($goodsIssue));
    }

    /**
     * Update a draft Goods Issue.
     */
    public function update(Request $request, GoodsIssue $goodsIssue): JsonResponse
    {
        if (!$goodsIssue->isDraft()) {
            return $this->error('Only draft Goods Issues can be updated.', 'INVALID_STATUS', 422);
        }

        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'gi_date'        => 'sometimes|date',
            'movement_type'  => ['sometimes', Rule::in([
                GoodsIssue::MOVEMENT_SALES_DELIVERY,
                GoodsIssue::MOVEMENT_PRODUCTION_ISSUE,
                GoodsIssue::MOVEMENT_SCRAPPING,
                GoodsIssue::MOVEMENT_TRANSFER,
                GoodsIssue::MOVEMENT_OTHER,
            ])],
            'warehouse_id'   => ['sometimes', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'branch_id'      => ['nullable', 'integer', Rule::exists('branches', 'id')->where('organization_id', $orgId)],
            'reference_type' => 'nullable|string|max:100',
            'reference_id'   => 'nullable|integer',
            'notes'          => 'nullable|string|max:2000',
            'lines'          => 'nullable|array|min:1',
            'lines.*.product_id'    => ['required_with:lines', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'lines.*.variant_id'    => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'lines.*.warehouse_id'  => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'lines.*.location_id'   => 'nullable|integer|exists:warehouse_locations,id',
            'lines.*.batch_id'      => 'nullable|integer|exists:inventory_batches,id',
            'lines.*.unit_id'       => 'nullable|integer|exists:units_of_measure,id',
            'lines.*.quantity'      => 'required_with:lines|numeric|min:0.0001',
            'lines.*.unit_cost'     => 'nullable|numeric|min:0',
            'lines.*.serial_number' => 'nullable|string|max:100',
            'lines.*.notes'         => 'nullable|string|max:255',
        ]);

        try {
            $gi = $this->goodsIssueService->update(
                $goodsIssue,
                collect($validated)->except('lines')->toArray(),
                $validated['lines'] ?? null
            );

            return $this->success(
                new \App\Http\Resources\Inventory\GoodsIssueResource($gi),
                'Goods Issue updated successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Post a draft Goods Issue (deducts stock and creates GL journal entry).
     */
    public function post(GoodsIssue $goodsIssue): JsonResponse
    {
        if (!$goodsIssue->canBePosted()) {
            return $this->error(
                'Goods Issue cannot be posted. Ensure it is in draft status and has at least one line.',
                'INVALID_STATUS',
                422
            );
        }

        try {
            $gi = $this->goodsIssueService->post($goodsIssue, auth()->id());

            return $this->success(
                new \App\Http\Resources\Inventory\GoodsIssueResource($gi),
                'Goods Issue posted successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'POSTING_FAILED', 422);
        }
    }

    /**
     * Reverse a posted Goods Issue (restores stock and reverses GL entry).
     */
    public function reverse(Request $request, GoodsIssue $goodsIssue): JsonResponse
    {
        if (!$goodsIssue->canBeReversed()) {
            return $this->error(
                'Only posted Goods Issues can be reversed.',
                'INVALID_STATUS',
                422
            );
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $gi = $this->goodsIssueService->reverse(
                $goodsIssue,
                $validated['reason'],
                auth()->id()
            );

            return $this->success(
                new \App\Http\Resources\Inventory\GoodsIssueResource($gi),
                'Goods Issue reversed successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }
}
