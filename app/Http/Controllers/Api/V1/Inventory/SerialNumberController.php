<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\SerialNumber;
use App\Services\Inventory\SerialNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SerialNumberController extends Controller
{
    public function __construct(
        private readonly SerialNumberService $serialNumberService,
    ) {}

    /**
     * List / search serial numbers.
     */
    public function index(Request $request): JsonResponse
    {
        $serials = $this->serialNumberService->search($request->only([
            'product_id',
            'warehouse_id',
            'status',
            'serial_number',
            'per_page',
        ]));

        return $this->paginated($serials);
    }

    /**
     * Create a single serial number.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serial_number'       => 'required|string|max:100',
            'product_id'          => 'required|exists:products,id',
            'product_variant_id'  => 'nullable|exists:product_variants,id',
            'batch_id'            => 'nullable|exists:inventory_batches,id',
            'warehouse_id'        => 'nullable|exists:warehouses,id',
            'location_id'         => 'nullable|exists:warehouse_locations,id',
            'manufacture_date'    => 'nullable|date',
            'expiry_date'         => 'nullable|date|after_or_equal:manufacture_date',
            'warranty_expiry_date' => 'nullable|date',
            'notes'               => 'nullable|string',
        ]);

        try {
            $sn = $this->serialNumberService->create($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($sn->load('product'), 'Serial number created successfully.');
    }

    /**
     * Show a specific serial number.
     */
    public function show(SerialNumber $serialNumber): JsonResponse
    {
        return $this->success(
            $serialNumber->load(['product', 'productVariant', 'batch', 'warehouse', 'location', 'soldTo'])
        );
    }

    /**
     * Soft-delete a serial number.
     */
    public function destroy(SerialNumber $serialNumber): JsonResponse
    {
        $serialNumber->delete();

        return $this->success(null, 'Serial number deleted.');
    }

    /**
     * Bulk-create serial numbers for a product.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'     => 'required|exists:products,id',
            'serial_numbers' => 'required|array|min:1',
            'serial_numbers.*' => 'required|string|max:100|distinct',
        ]);

        try {
            $created = $this->serialNumberService->bulkCreate(
                (int) $validated['product_id'],
                $validated['serial_numbers']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created([
            'created_count' => count($created),
            'serial_numbers' => $created,
        ], 'Serial numbers created successfully.');
    }

    /**
     * Receive a serial number into stock.
     */
    public function receive(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'  => 'required|exists:warehouses,id',
            'location_id'   => 'nullable|exists:warehouse_locations,id',
            'document_type' => 'required|string|max:50',
            'document_id'   => 'required|integer|min:1',
        ]);

        $this->serialNumberService->receive(
            $serialNumber,
            (int) $validated['warehouse_id'],
            isset($validated['location_id']) ? (int) $validated['location_id'] : null,
            $validated['document_type'],
            (int) $validated['document_id']
        );

        return $this->success($serialNumber->fresh(), 'Serial number received.');
    }

    /**
     * Issue / sell a serial number.
     */
    public function issue(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'  => 'required|exists:warehouses,id',
            'contact_id'    => 'nullable|exists:contacts,id',
            'document_type' => 'required|string|max:50',
            'document_id'   => 'required|integer|min:1',
        ]);

        try {
            $this->serialNumberService->issue(
                $serialNumber,
                (int) $validated['warehouse_id'],
                isset($validated['contact_id']) ? (int) $validated['contact_id'] : null,
                $validated['document_type'],
                (int) $validated['document_id']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success($serialNumber->fresh(), 'Serial number issued.');
    }

    /**
     * Transfer a serial number to another warehouse.
     */
    public function transfer(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $validated = $request->validate([
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'to_location_id'  => 'nullable|exists:warehouse_locations,id',
        ]);

        $this->serialNumberService->transfer(
            $serialNumber,
            (int) $validated['to_warehouse_id'],
            isset($validated['to_location_id']) ? (int) $validated['to_location_id'] : null
        );

        return $this->success($serialNumber->fresh(), 'Serial number transferred.');
    }

    /**
     * Scrap a serial number.
     */
    public function scrap(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->serialNumberService->scrap($serialNumber, $validated['reason']);

        return $this->success($serialNumber->fresh(), 'Serial number scrapped.');
    }

    /**
     * Full movement history for a serial number.
     */
    public function history(SerialNumber $serialNumber): JsonResponse
    {
        $movements = $this->serialNumberService->history($serialNumber);

        return $this->success($movements);
    }
}
