<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorConsignmentReceipt;
use App\Models\Purchase\VendorConsignmentSettlement;
use App\Models\Purchase\VendorConsignmentStock;
use App\Models\Purchase\VendorConsignmentWithdrawal;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendorConsignmentService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * Receive vendor-owned consignment stock into a warehouse.
     * Creates or updates the stock record and records the receipt movement.
     */
    public function receiveConsignmentStock(array $data): VendorConsignmentReceipt
    {
        return DB::transaction(function () use ($data): VendorConsignmentReceipt {
            $organizationId = auth()->user()->organization_id;

            $stock = VendorConsignmentStock::withoutGlobalScope('organization')
                ->where('organization_id', $organizationId)
                ->where('vendor_id', $data['vendor_id'])
                ->where('product_id', $data['product_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->first();

            if ($stock === null) {
                $stock = VendorConsignmentStock::create([
                    'organization_id'      => $organizationId,
                    'vendor_id'            => $data['vendor_id'],
                    'product_id'           => $data['product_id'],
                    'warehouse_id'         => $data['warehouse_id'],
                    'warehouse_location_id' => $data['warehouse_location_id'] ?? null,
                    'quantity_on_hand'     => 0,
                    'quantity_reserved'    => 0,
                    'unit_id'              => $data['unit_id'] ?? null,
                    'vendor_price'         => $data['vendor_price'],
                    'currency_code'        => $data['currency_code'],
                ]);
            }

            $quantityReceived = (float) $data['quantity_received'];

            if ($quantityReceived <= 0) {
                throw new \InvalidArgumentException('Quantity received must be positive.');
            }

            $receipt = VendorConsignmentReceipt::create([
                'organization_id'            => $organizationId,
                'vendor_consignment_stock_id' => $stock->id,
                'purchase_order_id'          => $data['purchase_order_id'] ?? null,
                'receipt_date'               => $data['receipt_date'],
                'quantity_received'          => $quantityReceived,
                'unit_id'                    => $data['unit_id'] ?? null,
                'vendor_delivery_note'       => $data['vendor_delivery_note'] ?? null,
                'notes'                      => $data['notes'] ?? null,
                'created_by'                 => auth()->id(),
            ]);

            $stock->update([
                'quantity_on_hand' => bcadd(
                    (string) $stock->quantity_on_hand,
                    (string) $quantityReceived,
                    4
                ),
                'last_movement_at' => now(),
            ]);

            return $receipt->load('consignmentStock');
        });
    }

    /**
     * Withdraw vendor-owned consignment stock for internal use.
     * Validates available quantity and updates the stock balance.
     */
    public function withdrawConsignmentStock(array $data): VendorConsignmentWithdrawal
    {
        return DB::transaction(function () use ($data): VendorConsignmentWithdrawal {
            $organizationId = auth()->user()->organization_id;

            /** @var VendorConsignmentStock $stock */
            $stock = VendorConsignmentStock::withoutGlobalScope('organization')
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->findOrFail($data['vendor_consignment_stock_id']);

            $quantityWithdrawn = (float) $data['quantity_withdrawn'];

            if ($quantityWithdrawn <= 0) {
                throw new \InvalidArgumentException('Quantity withdrawn must be positive.');
            }

            $available = $stock->getAvailableQuantity();

            if ($quantityWithdrawn > $available) {
                throw new ApiException(
                    ErrorCodes::INSUFFICIENT_STOCK,
                    "Insufficient consignment stock. Available: {$available}, requested: {$quantityWithdrawn}."
                );
            }

            $withdrawal = VendorConsignmentWithdrawal::create([
                'organization_id'            => $organizationId,
                'vendor_consignment_stock_id' => $stock->id,
                'withdrawal_date'            => $data['withdrawal_date'],
                'quantity_withdrawn'         => $quantityWithdrawn,
                'withdrawal_type'            => $data['withdrawal_type'],
                'reference_type'             => $data['reference_type'] ?? null,
                'reference_id'               => $data['reference_id'] ?? null,
                'unit_id'                    => $data['unit_id'] ?? null,
                'notes'                      => $data['notes'] ?? null,
                'created_by'                 => auth()->id(),
            ]);

            $stock->update([
                'quantity_on_hand' => bcsub(
                    (string) $stock->quantity_on_hand,
                    (string) $quantityWithdrawn,
                    4
                ),
                'last_movement_at' => now(),
            ]);

            return $withdrawal->load('consignmentStock');
        });
    }

    /**
     * Create a consignment settlement covering all withdrawals within a period for a vendor.
     */
    public function createSettlement(int $vendorId, string $periodFrom, string $periodTo): VendorConsignmentSettlement
    {
        return DB::transaction(function () use ($vendorId, $periodFrom, $periodTo): VendorConsignmentSettlement {
            $organizationId = auth()->user()->organization_id;

            // Collect all consignment stock records for this vendor.
            $stockIds = VendorConsignmentStock::withoutGlobalScope('organization')
                ->where('organization_id', $organizationId)
                ->where('vendor_id', $vendorId)
                ->pluck('id');

            if ($stockIds->isEmpty()) {
                throw new \InvalidArgumentException('No consignment stock found for this vendor.');
            }

            // Aggregate withdrawals in the period.
            $withdrawals = VendorConsignmentWithdrawal::withoutGlobalScope('organization')
                ->whereIn('vendor_consignment_stock_id', $stockIds)
                ->whereBetween('withdrawal_date', [$periodFrom, $periodTo])
                ->get();

            if ($withdrawals->isEmpty()) {
                throw new \InvalidArgumentException('No withdrawals found in the specified period.');
            }

            $totalQuantity = $withdrawals->sum('quantity_withdrawn');

            // Calculate total value by joining with stock's vendor_price.
            $totalValue = (string) '0';
            foreach ($withdrawals as $withdrawal) {
                $stock = VendorConsignmentStock::withoutGlobalScope('organization')
                    ->find($withdrawal->vendor_consignment_stock_id);
                if ($stock !== null) {
                    $lineValue = bcmul(
                        (string) $withdrawal->quantity_withdrawn,
                        (string) $stock->vendor_price,
                        4
                    );
                    $totalValue = bcadd($totalValue, $lineValue, 4);
                }
            }

            // Determine currency from first stock record.
            $firstStock = VendorConsignmentStock::withoutGlobalScope('organization')
                ->where('organization_id', $organizationId)
                ->where('vendor_id', $vendorId)
                ->first();

            $settlement = VendorConsignmentSettlement::create([
                'organization_id'       => $organizationId,
                'vendor_id'             => $vendorId,
                'settlement_period_from' => $periodFrom,
                'settlement_period_to'  => $periodTo,
                'total_quantity'        => $totalQuantity,
                'total_value'           => $totalValue,
                'currency_code'         => $firstStock?->currency_code ?? 'SAR',
                'status'                => VendorConsignmentSettlement::STATUS_DRAFT,
            ]);

            return $settlement->load('vendor');
        });
    }

    /**
     * Submit a draft settlement, creating a vendor bill for the total value.
     */
    public function submitSettlement(VendorConsignmentSettlement $settlement): void
    {
        DB::transaction(function () use ($settlement): void {
            if (!$settlement->isDraft()) {
                throw new \InvalidArgumentException('Only draft settlements can be submitted.');
            }

            $bill = Bill::create([
                'organization_id'         => $settlement->organization_id,
                'supplier_id'             => $settlement->vendor_id,
                'supplier_name'           => $settlement->vendor?->getDisplayName() ?? 'Vendor',
                'bill_number'             => $this->numberGenerator->generate('BILL'),
                'bill_date'               => now()->toDateString(),
                'due_date'                => now()->addDays(30)->toDateString(),
                'currency_code'           => $settlement->currency_code,
                'subtotal'                => $settlement->total_value,
                'tax_amount'              => 0,
                'total'                   => $settlement->total_value,
                'status'                  => Bill::STATUS_DRAFT,
                'notes'                   => "Consignment settlement {$settlement->settlement_period_from} – {$settlement->settlement_period_to}",
                'created_by'              => auth()->id(),
            ]);

            $settlement->update([
                'status'     => VendorConsignmentSettlement::STATUS_SUBMITTED,
                'bill_id'    => $bill->id,
                'settled_at' => now(),
                'settled_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Return all consignment stock records for a vendor.
     */
    public function getStockByVendor(int $vendorId): Collection
    {
        return VendorConsignmentStock::with(['product', 'warehouse', 'unit'])
            ->forVendor($vendorId)
            ->get();
    }

    /**
     * Return all consignment stock records for a product.
     */
    public function getStockByProduct(int $productId): Collection
    {
        return VendorConsignmentStock::with(['vendor', 'warehouse', 'unit'])
            ->forProduct($productId)
            ->get();
    }

    /**
     * Calculate the total unsettled consignment value owed to a vendor.
     */
    public function getPendingSettlementValue(int $vendorId): float
    {
        $organizationId = auth()->user()->organization_id;

        $stockIds = VendorConsignmentStock::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('vendor_id', $vendorId)
            ->pluck('id', 'id');

        if ($stockIds->isEmpty()) {
            return 0.0;
        }

        $withdrawals = VendorConsignmentWithdrawal::withoutGlobalScope('organization')
            ->whereIn('vendor_consignment_stock_id', $stockIds->keys())
            ->unsettled()
            ->get();

        $total = '0';
        foreach ($withdrawals as $withdrawal) {
            $stock = $stockIds->has($withdrawal->vendor_consignment_stock_id)
                ? VendorConsignmentStock::withoutGlobalScope('organization')
                    ->find($withdrawal->vendor_consignment_stock_id)
                : null;

            if ($stock !== null) {
                $total = bcadd(
                    $total,
                    bcmul((string) $withdrawal->quantity_withdrawn, (string) $stock->vendor_price, 4),
                    4
                );
            }
        }

        return (float) $total;
    }
}
