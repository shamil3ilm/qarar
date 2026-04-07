<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Models\Sales\ReturnPolicy;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use App\Models\Sales\ExchangeOrder;
use App\Models\Inventory\StockMovement;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;

class SalesReturnService
{
    public function __construct(
        private RefundService $refundService,
        private CreditNoteService $creditNoteService,
        private NumberGeneratorService $numberGenerator,
        private StockService $stockService,
    ) {}

    public function create(array $data, int $userId): SalesReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            // Validate against return policy
            if (! empty($data['invoice_id'])) {
                try {
                    $this->validateReturnPolicy($data);
                } catch (ApiException $e) {
                    throw $e; // Re-throw business exceptions
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Return policy validation skipped: ' . $e->getMessage());
                }
            }

            $items = $data['items'] ?? [];
            unset($data['items']);

            $salesReturn = SalesReturn::create(array_merge($data, [
                'return_number' => $this->generateReturnNumber($data['organization_id']),
                'status' => SalesReturn::STATUS_PENDING,
                'created_by' => $userId,
            ]));

            if (! empty($items)) {
                foreach ($items as $item) {
                    // Validate product existence
                    if (! empty($item['product_id'])) {
                        \App\Models\Inventory\Product::findOrFail($item['product_id']);
                    }

                    // Strip non-column fields that may come from the request
                    unset($item['quantity'], $item['reason']);

                    $subtotal = bcmul((string) $item['quantity_returned'], (string) $item['unit_price'], 2);
                    $taxAmount = bcmul($subtotal, bcdiv((string) ($item['tax_rate'] ?? 0), '100', 4), 2);

                    $salesReturn->items()->create(array_merge($item, [
                        'subtotal' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'total' => bcadd($subtotal, $taxAmount, 2),
                        'item_status' => SalesReturnItem::STATUS_PENDING,
                    ]));
                }

                $salesReturn->load('items');
                $salesReturn->calculateTotals();
            }

            // Apply restocking fee if configured
            try {
                $this->applyRestockingFee($salesReturn);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Restocking fee application skipped: ' . $e->getMessage());
            }

            return $salesReturn->fresh(['items', 'customer', 'invoice']);
        });
    }

    public function approve(SalesReturn $salesReturn, int $userId): SalesReturn
    {
        if ($salesReturn->status !== SalesReturn::STATUS_PENDING) {
            throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION);
        }

        $salesReturn->approve($userId);

        return $salesReturn->fresh();
    }

    public function reject(SalesReturn $salesReturn, int $userId, string $reason): SalesReturn
    {
        if ($salesReturn->status !== SalesReturn::STATUS_PENDING) {
            throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION);
        }

        $salesReturn->reject($userId, $reason);

        return $salesReturn->fresh();
    }

    public function receiveItems(SalesReturn $salesReturn, array $receivedItems): SalesReturn
    {
        if (! in_array($salesReturn->status, [SalesReturn::STATUS_APPROVED, SalesReturn::STATUS_RECEIVED])) {
            throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION);
        }

        return DB::transaction(function () use ($salesReturn, $receivedItems) {
            // Validate that received quantities do not exceed the original invoiced quantities
            if ($salesReturn->invoice_id) {
                foreach ($receivedItems as $itemData) {
                    $returnItem = $salesReturn->items()->find($itemData['id']);
                    if ($returnItem && $returnItem->product_id) {
                        $invoiceLine = InvoiceLine::where('invoice_id', $salesReturn->invoice_id)
                            ->where('product_id', $returnItem->product_id)
                            ->first();
                        if ($invoiceLine && bccomp((string) ($itemData['quantity_received'] ?? 0), (string) $invoiceLine->quantity, 4) > 0) {
                            throw new \InvalidArgumentException(
                                "Return quantity exceeds invoiced quantity for product {$returnItem->product_id}"
                            );
                        }
                    }
                }
            }

            foreach ($receivedItems as $itemData) {
                $item = $salesReturn->items()->find($itemData['id']);
                if ($item) {
                    $item->update([
                        'quantity_received' => $itemData['quantity_received'],
                        'quantity_damaged' => $itemData['quantity_damaged'] ?? 0,
                        'condition' => $itemData['condition'] ?? null,
                        'condition_notes' => $itemData['condition_notes'] ?? null,
                        'item_status' => SalesReturnItem::STATUS_RECEIVED,
                    ]);
                }
            }

            $salesReturn->markReceived();

            return $salesReturn->fresh(['items']);
        });
    }

    public function inspect(SalesReturn $salesReturn, string $inspectionStatus, ?string $notes = null): SalesReturn
    {
        if ($salesReturn->status !== SalesReturn::STATUS_RECEIVED) {
            throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION);
        }

        $salesReturn->update([
            'status' => SalesReturn::STATUS_INSPECTED,
            'inspection_status' => $inspectionStatus,
            'inspection_notes' => $notes,
        ]);

        return $salesReturn->fresh();
    }

    public function resolve(SalesReturn $salesReturn, string $resolutionType, int $userId): SalesReturn
    {
        if (! in_array($salesReturn->status, [SalesReturn::STATUS_INSPECTED, SalesReturn::STATUS_RECEIVED])) {
            throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION);
        }

        return DB::transaction(function () use ($salesReturn, $resolutionType, $userId) {
            // Lock the return row to prevent concurrent resolution races
            $salesReturn = SalesReturn::lockForUpdate()->findOrFail($salesReturn->id);
            $salesReturn->update(['resolution_type' => $resolutionType]);

            switch ($resolutionType) {
                case SalesReturn::RESOLUTION_FULL_REFUND:
                    $salesReturn->update(['refund_amount' => $salesReturn->total]);
                    $refund = $this->refundService->createFromSalesReturn($salesReturn, 'original_payment_method', $userId);
                    $salesReturn->update(['refund_id' => $refund->id]);
                    break;

                case SalesReturn::RESOLUTION_PARTIAL_REFUND:
                    $refund = $this->refundService->createFromSalesReturn($salesReturn, 'original_payment_method', $userId);
                    $salesReturn->update(['refund_id' => $refund->id]);
                    break;

                case SalesReturn::RESOLUTION_CREDIT_NOTE:
                    try {
                        $salesReturn->load('items');
                        $creditNote = $this->createCreditNoteFromReturn($salesReturn, $userId);
                        $salesReturn->update(['credit_note_id' => $creditNote->id]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning('Credit note creation from return skipped: ' . $e->getMessage());
                    }
                    break;

                case SalesReturn::RESOLUTION_EXCHANGE:
                    // Exchange order created separately via createExchange()
                    break;

                case SalesReturn::RESOLUTION_REJECTED:
                    $salesReturn->reject($userId, 'Items failed inspection');
                    return $salesReturn->fresh();
            }

            // Restock items if applicable
            if ($salesReturn->restock_items) {
                try {
                    $this->restockItems($salesReturn);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Restock items skipped: ' . $e->getMessage());
                }
            }

            $salesReturn->update(['status' => SalesReturn::STATUS_COMPLETED]);

            return $salesReturn->fresh();
        });
    }

    public function createExchange(SalesReturn $salesReturn, array $exchangeItems, int $userId): ExchangeOrder
    {
        return DB::transaction(function () use ($salesReturn, $exchangeItems, $userId) {
            $originalTotal = (float) $salesReturn->total;
            $exchangeTotal = 0;

            $items = [];
            foreach ($exchangeItems as $item) {
                $itemTotal = bcmul((string) $item['replacement_quantity'], (string) $item['replacement_unit_price'], 2);
                $exchangeTotal = bcadd((string) $exchangeTotal, $itemTotal, 2);
                $items[] = $item;
            }

            $exchange = ExchangeOrder::create([
                'organization_id' => $salesReturn->organization_id,
                'exchange_number' => $this->generateExchangeNumber($salesReturn->organization_id),
                'sales_return_id' => $salesReturn->id,
                'customer_id' => $salesReturn->customer_id,
                'original_total' => $originalTotal,
                'exchange_total' => $exchangeTotal,
                'price_difference' => bcsub((string) $exchangeTotal, (string) $originalTotal, 2),
                'status' => ExchangeOrder::STATUS_PENDING,
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                try {
                    $exchange->items()->create($item);
                } catch (\Exception $e) {
                    // Item creation may fail if product references are missing
                    \Illuminate\Support\Facades\Log::warning('Exchange item creation skipped: ' . $e->getMessage());
                }
            }

            $salesReturn->update([
                'resolution_type' => SalesReturn::RESOLUTION_EXCHANGE,
                'exchange_order_id' => $exchange->id,
            ]);

            return $exchange->fresh(['items']);
        });
    }

    public function list(int $organizationId, array $filters = [], int $perPage = 20)
    {
        $query = SalesReturn::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['return_type'])) {
            $query->where('return_type', $filters['return_type']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('return_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('return_date', '<=', $filters['to_date']);
        }

        return $query->with(['customer', 'invoice', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    private function validateReturnPolicy(array $data): void
    {
        $invoice = Invoice::find($data['invoice_id']);
        if (! $invoice) {
            return;
        }

        $policy = ReturnPolicy::where('organization_id', $data['organization_id'])
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (! $policy) {
            return;
        }

        if (! $policy->isWithinReturnWindow($invoice->invoice_date)) {
            throw ApiException::fromError(ErrorCodes::BIZ_RETURN_WINDOW_EXPIRED, [
                'return_window_days' => $policy->return_window_days,
                'invoice_date' => $invoice->invoice_date->toDateString(),
            ]);
        }
    }

    private function applyRestockingFee(SalesReturn $salesReturn): void
    {
        $policy = ReturnPolicy::where('organization_id', $salesReturn->organization_id)
            ->active()
            ->default()
            ->first();

        if (! $policy || $policy->restocking_fee_percent <= 0) {
            return;
        }

        $fee = $policy->calculateRestockingFee((float) $salesReturn->subtotal);
        $total = bcsub((string) $salesReturn->total, (string) $fee, 2);

        $salesReturn->update([
            'restocking_fee' => $fee,
            'total' => $total,
        ]);
    }

    private function restockItems(SalesReturn $salesReturn): void
    {
        foreach ($salesReturn->items as $item) {
            if ($item->isRestockable() && $item->quantity_received > 0) {
                $restockQty = bcsub(
                    (string) $item->quantity_received,
                    (string) $item->quantity_damaged,
                    4
                );

                if (bccomp($restockQty, '0', 4) > 0) {
                    $item->update([
                        'quantity_restocked' => $restockQty,
                        'item_status' => SalesReturnItem::STATUS_RESTOCKED,
                    ]);

                    if ($item->product_id && $salesReturn->warehouse_id) {
                        $this->stockService->recordMovement(
                            productId: $item->product_id,
                            warehouseId: $salesReturn->warehouse_id,
                            movementType: StockMovement::TYPE_RETURN_IN,
                            direction: StockMovement::DIRECTION_IN,
                            quantity: (float) $restockQty,
                            unitCost: (float) $item->unit_price,
                            variantId: $item->variant_id,
                            referenceType: SalesReturn::class,
                            referenceId: $salesReturn->id,
                            referenceNumber: $salesReturn->return_number,
                            notes: "Restock from sales return #{$salesReturn->return_number}",
                        );
                    }
                }
            }
        }
    }

    private function createCreditNoteFromReturn(SalesReturn $salesReturn, int $userId)
    {
        $items = $salesReturn->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description ?? "Return #{$salesReturn->return_number}",
            'quantity' => $item->quantity_returned,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
        ])->toArray();

        return $this->creditNoteService->create([
            'organization_id' => $salesReturn->organization_id,
            'credit_note_type' => 'sales',
            'invoice_id' => $salesReturn->invoice_id,
            'contact_id' => $salesReturn->customer_id,
            'credit_note_date' => now()->toDateString(),
            'currency_code' => $salesReturn->currency_code,
            'reason' => "Sales Return #{$salesReturn->return_number}",
            'items' => $items,
        ], $userId);
    }

    private function generateReturnNumber(int $organizationId): string
    {
        return $this->numberGenerator->generate('sales_return');
    }

    private function generateExchangeNumber(int $organizationId): string
    {
        $count = ExchangeOrder::where('organization_id', $organizationId)->count() + 1;
        return 'EXC-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
