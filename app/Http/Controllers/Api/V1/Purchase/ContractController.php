<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\ContractResource;
use App\Models\Purchase\Contract;
use App\Services\Purchase\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(
        private ContractService $contractService
    ) {}

    /**
     * List contracts with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contract::with(['contact', 'creator'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->contract_type, fn($q, $t) => $q->where('contract_type', $t))
            ->when($request->contact_id, fn($q, $id) => $q->where('contact_id', $id))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('contract_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($request->expiring_in_days, fn($q, $days) => $q->expiringSoon((int) $days))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['contract_number', 'title', 'start_date', 'end_date', 'status', 'total_value', 'created_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        return $this->paginated($query->paginate($request->integer('per_page', 15)), ContractResource::class);
    }

    /**
     * Create a new contract.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contract_number' => 'nullable|string|max:30',
            'contract_type' => 'required|in:sales,purchase,service,maintenance',
            'contact_id' => 'required|exists:contacts,id',
            'title' => 'required|string|max:200',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'auto_renew' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer|min:0',
            'currency_code' => 'required|string|size:3',
            'total_value' => 'nullable|numeric|min:0',
            'signed_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'parent_contract_id' => 'nullable|exists:contracts,id',
            'lines' => 'nullable|array',
            'lines.*.product_id' => 'nullable|exists:products,id',
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.line_total' => 'nullable|numeric|min:0',
            'lines.*.unit_id' => 'nullable|exists:units_of_measure,id',
            'lines.*.delivery_schedule' => 'nullable|array',
            'lines.*.sort_order' => 'nullable|integer',
            'milestones' => 'nullable|array',
            'milestones.*.milestone_name' => 'required|string|max:200',
            'milestones.*.due_date' => 'required|date',
            'milestones.*.amount' => 'required|numeric|min:0',
            'milestones.*.notes' => 'nullable|string',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        try {
            $contract = $this->contractService->createContract($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new ContractResource($contract), 'Contract created successfully.');
    }

    /**
     * Show a contract with all related data.
     */
    public function show(Contract $contract): JsonResponse
    {
        return $this->success(
            new ContractResource(
                $contract->load(['contact', 'lines.product', 'milestones', 'releases', 'documents', 'creator', 'parentContract'])
            )
        );
    }

    /**
     * Update a draft contract.
     */
    public function update(Request $request, Contract $contract): JsonResponse
    {
        if ($contract->status !== Contract::STATUS_DRAFT) {
            return $this->error('Only draft contracts can be updated.', 'VALIDATION_ERROR', 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'auto_renew' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer|min:0',
            'currency_code' => 'sometimes|string|size:3',
            'total_value' => 'nullable|numeric|min:0',
            'signed_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $contract->update($validated);

        return $this->success(new ContractResource($contract->fresh(['contact', 'lines'])), 'Contract updated successfully.');
    }

    /**
     * Delete a draft contract.
     */
    public function destroy(Contract $contract): JsonResponse
    {
        if ($contract->status !== Contract::STATUS_DRAFT) {
            return $this->error('Only draft contracts can be deleted.', 'VALIDATION_ERROR', 422);
        }

        $contract->lines()->delete();
        $contract->milestones()->delete();
        $contract->delete();

        return $this->success(null, 'Contract deleted successfully.');
    }

    /**
     * Activate a draft contract.
     */
    public function activate(Contract $contract): JsonResponse
    {
        return $this->tryAction(
            fn() => new ContractResource($this->contractService->activateContract($contract)),
            'Contract activated successfully.'
        );
    }

    /**
     * Create a release order against a contract.
     */
    public function createRelease(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'nullable|string|max:50',
            'source_id' => 'nullable|integer',
            'release_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        return $this->tryAction(
            fn() => $this->contractService->createRelease($contract, $validated)->toArray(),
            'Contract release created successfully.'
        );
    }

    /**
     * List releases for a contract.
     */
    public function indexReleases(Contract $contract): JsonResponse
    {
        $releases = $contract->releases()->orderBy('release_date', 'desc')->get();

        return $this->success($releases->toArray());
    }

    /**
     * Terminate a contract.
     */
    public function terminate(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        return $this->tryAction(
            fn() => new ContractResource($this->contractService->terminateContract($contract, $validated)),
            'Contract terminated successfully.'
        );
    }

    /**
     * Get contracts expiring within specified days.
     */
    public function expiringContracts(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $organization = $this->organization($request);

        if (!$organization) {
            return $this->error('Organization context required.', 'UNAUTHORIZED', 401);
        }

        $contracts = $this->contractService->checkExpiringContracts($organization, $days);

        return $this->success($contracts->map(fn($c) => new ContractResource($c))->values()->toArray());
    }
}
