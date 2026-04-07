<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Core\Organization;
use App\Models\Purchase\VendorContract;
use App\Services\Purchase\VendorContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorContractController extends Controller
{
    public function __construct(
        private readonly VendorContractService $contractService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $contracts = VendorContract::where('organization_id', $request->user()->organization_id)
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('contact_id'), fn ($q, $c) => $q->where('contact_id', $c))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($contracts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'           => 'required|integer',
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string',
            'contract_type'        => 'nullable|in:supply,service,framework,blanket_order',
            'currency_code'        => 'nullable|string|size:3',
            'total_value'          => 'nullable|numeric|min:0',
            'start_date'           => 'required|date',
            'end_date'             => 'nullable|date|after_or_equal:start_date',
            'auto_renew'           => 'nullable|boolean',
            'renewal_notice_days'  => 'nullable|integer|min:1',
            'payment_terms'        => 'nullable|string',
            'signed_at'            => 'nullable|date',
            'notes'                => 'nullable|string',
            'items'                => 'nullable|array',
            'items.*.product_id'   => 'nullable|integer',
            'items.*.description'  => 'required_with:items|string',
            'items.*.unit_price'   => 'required_with:items|numeric|min:0',
            'items.*.quantity'     => 'nullable|numeric|min:0',
            'items.*.unit_of_measure' => 'nullable|string|max:20',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['created_by']      = $request->user()->id;

        $contract = $this->contractService->create($validated);

        return $this->created($contract->load('items'), 'Vendor contract created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $contract = VendorContract::where('organization_id', $request->user()->organization_id)
            ->with(['items', 'contact'])
            ->findOrFail($id);

        return $this->success($contract);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $contract = VendorContract::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $activated = $this->contractService->activate($contract);

        return $this->success($activated, 'Contract activated.');
    }

    public function terminate(Request $request, int $id): JsonResponse
    {
        $contract = VendorContract::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $terminated = $this->contractService->terminate($contract, $validated['reason']);

        return $this->success($terminated, 'Contract terminated.');
    }

    public function expiring(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $org  = Organization::findOrFail($request->user()->organization_id);

        $contracts = $this->contractService->getExpiringContracts($org, $days);

        return $this->success($contracts);
    }
}
