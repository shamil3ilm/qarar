<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\PriceList;
use App\Models\Sales\PriceListAssignment;
use App\Models\Sales\PriceListItem;
use App\Models\Sales\PriceVolumeBreak;
use App\Services\Sales\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function __construct(
        private PriceListService $priceListService
    ) {}

    /**
     * List price lists with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PriceList::query()->latest()
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('currency_code'), fn($q) => $q->where('currency_code', $request->input('currency_code')))
            ->when($request->has('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->boolean('valid_now'), function ($q) {
                $today = now()->toDateString();
                $q->where('valid_from', '<=', $today)
                  ->where(function ($q) use ($today) {
                      $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
                  });
            });

        $priceLists = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($priceLists);
    }

    /**
     * Create a new price list.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'code'          => 'required|string|max:30',
            'currency_code' => 'required|string|size:3',
            'valid_from'    => 'required|date',
            'valid_to'      => 'nullable|date|after_or_equal:valid_from',
            'is_default'    => 'boolean',
            'description'   => 'nullable|string|max:2000',
            'is_active'     => 'boolean',
            'items'         => 'nullable|array',
            'items.*.product_id'   => 'required|integer|exists:products,id',
            'items.*.variant_id'   => 'nullable|integer|exists:product_variants,id',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.min_quantity' => 'nullable|numeric|min:0',
            'items.*.discount_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.notes'        => 'nullable|string|max:200',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $validated['created_by']      = auth()->id();

        $priceList = $this->priceListService->createPriceList($validated);

        return $this->success($priceList->load(['items', 'assignments']), 'Price list created.', 201);
    }

    /**
     * Show a price list with its items, assignments and volume breaks.
     */
    public function show(Request $request, PriceList $priceList): JsonResponse
    {
        $items          = PriceListItem::where('price_list_id', $priceList->id)->get();
        $assignments    = PriceListAssignment::where('price_list_id', $priceList->id)->get();
        $volumeBreaks   = PriceVolumeBreak::where('price_list_id', $priceList->id)->get();

        return $this->success([
            'price_list'    => $priceList,
            'items'         => $items,
            'assignments'   => $assignments,
            'volume_breaks' => $volumeBreaks,
        ]);
    }

    /**
     * Update a price list.
     */
    public function update(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:100',
            'currency_code' => 'sometimes|string|size:3',
            'valid_from'    => 'sometimes|date',
            'valid_to'      => 'nullable|date',
            'is_default'    => 'boolean',
            'description'   => 'nullable|string|max:2000',
            'is_active'     => 'boolean',
            'items'         => 'nullable|array',
            'items.*.product_id'   => 'required|integer|exists:products,id',
            'items.*.variant_id'   => 'nullable|integer|exists:product_variants,id',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.min_quantity' => 'nullable|numeric|min:0',
            'items.*.discount_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.notes'        => 'nullable|string|max:200',
        ]);

        $priceList = $this->priceListService->updatePriceList($priceList, $validated);

        return $this->success($priceList, 'Price list updated.');
    }

    /**
     * Soft-delete a price list.
     */
    public function destroy(PriceList $priceList): JsonResponse
    {
        $priceList->delete();

        return $this->success(null, 'Price list deleted.');
    }

    /**
     * Assign a price list to a specific contact.
     */
    public function assignToContact(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
        ]);

        $contact    = Contact::findOrFail($validated['contact_id']);
        $assignment = $this->priceListService->assignToContact($priceList, $contact);

        return $this->success($assignment, 'Price list assigned to contact.');
    }

    /**
     * Resolve the effective price for a contact + product + quantity combination.
     * Query parameters: contact_id, product_id, quantity, currency
     */
    public function resolvePrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'nullable|numeric|min:0',
            'currency'   => 'nullable|string|size:3',
        ]);

        $contact  = Contact::findOrFail($validated['contact_id']);
        $product  = Product::findOrFail($validated['product_id']);
        $quantity = (float) ($validated['quantity'] ?? 1);
        $currency = $validated['currency'] ?? null;

        $result = $this->priceListService->resolvePrice($contact, $product, $quantity, $currency);

        if ($result === null) {
            return $this->error('No applicable price list found for the given parameters.', 404);
        }

        return $this->success($result);
    }

    /**
     * Bulk-import price list items.
     */
    public function importItems(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer|exists:products,id',
            'items.*.variant_id'   => 'nullable|integer|exists:product_variants,id',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.min_quantity' => 'nullable|numeric|min:0',
            'items.*.discount_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.notes'        => 'nullable|string|max:200',
        ]);

        $count = $this->priceListService->importItems($priceList, $validated['items']);

        return $this->success(['imported' => $count], "{$count} items imported.");
    }
}
