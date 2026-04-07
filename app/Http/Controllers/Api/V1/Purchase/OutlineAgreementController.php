<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\OutlineAgreement;
use App\Models\Purchase\OutlineAgreementItem;
use App\Services\Purchase\OutlineAgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OutlineAgreementController extends Controller
{
    public function __construct(private readonly OutlineAgreementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $agreements = $this->service->list(
            (int) Auth::user()->organization_id,
            $request->only(['vendor_id', 'status', 'agreement_type', 'per_page'])
        );

        return $this->success($agreements, 'Outline agreements retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'        => 'required|integer|exists:contacts,id',
            'agreement_number' => 'required|string|max:50',
            'agreement_type'   => 'required|in:quantity_contract,value_contract,scheduling_agreement',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'currency_code'    => 'nullable|string|size:3',
            'target_quantity'  => 'nullable|numeric|min:0',
            'target_value'     => 'nullable|numeric|min:0',
            'payment_terms'    => 'nullable|string|max:100',
            'delivery_days'    => 'nullable|integer|min:0',
            'notes'            => 'nullable|string',
        ]);

        $agreement = $this->service->create(
            (int) Auth::user()->organization_id,
            $validated
        );

        return $this->created($agreement->load(['vendor', 'items']), 'Outline agreement created.');
    }

    public function show(string $id): JsonResponse
    {
        $agreement = OutlineAgreement::where('organization_id', Auth::user()->organization_id)
            ->with(['vendor', 'items.product', 'releases.purchaseOrder'])
            ->findOrFail($id);

        return $this->success($agreement, 'Outline agreement retrieved.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $agreement = OutlineAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'valid_from'    => 'sometimes|date',
            'valid_to'      => 'nullable|date|after_or_equal:valid_from',
            'currency_code' => 'nullable|string|size:3',
            'target_quantity' => 'nullable|numeric|min:0',
            'target_value'  => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:100',
            'delivery_days' => 'nullable|integer|min:0',
            'notes'         => 'nullable|string',
        ]);

        $updated = $this->service->update($agreement, $validated);

        return $this->success($updated, 'Outline agreement updated.');
    }

    public function destroy(string $id): JsonResponse
    {
        $agreement = OutlineAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $agreement->delete();

        return $this->success(null, 'Outline agreement deleted.');
    }

    public function addItem(Request $request, string $id): JsonResponse
    {
        $agreement = OutlineAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'product_id'      => 'nullable|integer|exists:products,id',
            'line_number'     => 'required|integer|min:1',
            'description'     => 'nullable|string',
            'target_quantity' => 'nullable|numeric|min:0',
            'target_value'    => 'nullable|numeric|min:0',
            'unit_price'      => 'nullable|numeric|min:0',
            'unit_of_measure' => 'nullable|string|max:20',
        ]);

        $item = $this->service->addItem($agreement, $validated);

        return $this->created($item->load('product'), 'Item added.');
    }

    public function updateItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $agreement = OutlineAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $item = OutlineAgreementItem::where('outline_agreement_id', $agreement->id)
            ->findOrFail($itemId);

        $validated = $request->validate([
            'description'     => 'nullable|string',
            'target_quantity' => 'nullable|numeric|min:0',
            'target_value'    => 'nullable|numeric|min:0',
            'unit_price'      => 'nullable|numeric|min:0',
            'unit_of_measure' => 'nullable|string|max:20',
        ]);

        $updated = $this->service->updateItem($item, $validated);

        return $this->success($updated, 'Item updated.');
    }

    public function createRelease(Request $request, string $id): JsonResponse
    {
        $agreement = OutlineAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'outline_agreement_item_id' => 'nullable|integer|exists:outline_agreement_items,id',
            'purchase_order_id'         => 'nullable|integer|exists:purchase_orders,id',
            'release_date'              => 'required|date',
            'release_quantity'          => 'nullable|numeric|min:0',
            'release_value'             => 'nullable|numeric|min:0',
        ]);

        $release = $this->service->createRelease($agreement, $validated);

        return $this->created($release, 'Release created.');
    }

    public function getReleases(string $id): JsonResponse
    {
        $agreement = OutlineAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $releases = $agreement->releases()->with(['item.product', 'purchaseOrder'])->get();

        return $this->success($releases, 'Releases retrieved.');
    }

    public function activate(string $id): JsonResponse
    {
        $agreement = OutlineAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $updated = $this->service->activate($agreement);

        return $this->success($updated, 'Outline agreement activated.');
    }

    public function cancel(string $id): JsonResponse
    {
        $agreement = OutlineAgreement::where('organization_id', Auth::user()->organization_id)
            ->findOrFail($id);

        $updated = $this->service->cancel($agreement);

        return $this->success($updated, 'Outline agreement cancelled.');
    }
}
