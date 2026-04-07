<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\StockService;
use App\Services\Tax\TaxCalculatorService;
use App\Traits\StructuredLogger;
use Illuminate\Support\Facades\DB;

class BillService
{
    use StructuredLogger;
    public function __construct(
        private TaxCalculatorService $taxCalculator,
        private JournalService $journalService,
        private JournalEntryFactory $journalEntryFactory,
        private StockService $stockService,
        private NumberGeneratorService $numberGenerator,
        private GoodsReceiptService $goodsReceiptService
    ) {}

    /**
     * Create a new bill.
     */
    public function create(array $data, array $lines): Bill
    {
        return DB::transaction(function () use ($data, $lines) {
            $organization = Organization::findOrFail(auth()->user()->organization_id);
            $supplier = Contact::where('organization_id', auth()->user()->organization_id)
                ->findOrFail($data['supplier_id']);

            if (empty($data['bill_number'])) {
                $prefix = ($data['bill_type'] ?? Bill::TYPE_STANDARD) === Bill::TYPE_DEBIT_NOTE ? 'DN' : 'BILL';
                $data['bill_number'] = $this->numberGenerator->generate($prefix);
            }

            $data['supplier_name'] = $supplier->getDisplayName();
            $data['supplier_tax_number'] = $supplier->tax_number;
            $data['supplier_address'] = $data['supplier_address'] ?? $supplier->getBillingAddress();

            $data['currency_code'] = $data['currency_code'] ?? $supplier->currency_code ?? $organization->base_currency;
            if (isset($data['exchange_rate']) && bccomp((string) $data['exchange_rate'], '0', 4) <= 0) {
                throw new \InvalidArgumentException('Exchange rate must be positive.');
            }
            $data['exchange_rate'] = $data['exchange_rate'] ?? 1;
            $data['due_date'] = $data['due_date'] ?? now()->addDays($supplier->payment_terms);
            $data['status'] = $data['status'] ?? Bill::STATUS_DRAFT;

            $bill = Bill::create($data);

            $taxResult = $this->taxCalculator->calculate(
                $organization,
                $lines,
                $data['place_of_supply'] ?? null
            );

            foreach ($lines as $index => $lineData) {
                if ((float) ($lineData['quantity'] ?? 0) <= 0 || (float) ($lineData['unit_price'] ?? 0) < 0) {
                    throw new \InvalidArgumentException('Bill line quantity must be positive and unit price cannot be negative.');
                }

                $taxLine = $taxResult->lines[$index] ?? [];

                if (empty($lineData['description'])) {
                    $product = isset($lineData['product_id'])
                        ? \App\Models\Inventory\Product::withoutGlobalScopes()
                            ->where('organization_id', $bill->organization_id)
                            ->find($lineData['product_id'])
                        : null;
                    $lineData['description'] = $product?->name ?? '';
                }

                $bill->lines()->create(array_merge($lineData, [
                    'tax_rate' => $taxLine['tax_rate'] ?? 0,
                    'tax_amount' => $taxLine['tax_amount'] ?? 0,
                    'tax_code' => $taxLine['tax_code'] ?? 'S',
                    'cgst_rate' => $taxLine['cgst_rate'] ?? 0,
                    'cgst_amount' => $taxLine['cgst_amount'] ?? 0,
                    'sgst_rate' => $taxLine['sgst_rate'] ?? 0,
                    'sgst_amount' => $taxLine['sgst_amount'] ?? 0,
                    'igst_rate' => $taxLine['igst_rate'] ?? 0,
                    'igst_amount' => $taxLine['igst_amount'] ?? 0,
                    'line_order' => $index,
                ]));
            }

            $bill->recalculateTotals();

            return $bill->load('lines', 'supplier');
        });
    }

    /**
     * Update a draft bill.
     */
    public function update(Bill $bill, array $data, ?array $lines = null): Bill
    {
        if (!$bill->isEditable()) {
            throw new \InvalidArgumentException('Only draft/pending bills can be updated.');
        }

        return DB::transaction(function () use ($bill, $data, $lines) {
            if (isset($data['version']) && $data['version'] !== $bill->version) {
                throw new \App\Exceptions\ConcurrencyException(
                    'Bill has been modified by another user.',
                    $bill
                );
            }

            $bill->update(array_merge($data, ['version' => $bill->version + 1]));

            if ($lines !== null) {
                $organization = Organization::findOrFail($bill->organization_id);
                $bill->lines()->delete();

                $taxResult = $this->taxCalculator->calculate(
                    $organization,
                    $lines,
                    $bill->place_of_supply
                );

                foreach ($lines as $index => $lineData) {
                    if ((float) ($lineData['quantity'] ?? 0) <= 0 || (float) ($lineData['unit_price'] ?? 0) < 0) {
                        throw new \InvalidArgumentException('Bill line quantity must be positive and unit price cannot be negative.');
                    }

                    $taxLine = $taxResult->lines[$index] ?? [];

                    $bill->lines()->create(array_merge($lineData, [
                        'tax_rate' => $taxLine['tax_rate'] ?? 0,
                        'tax_amount' => $taxLine['tax_amount'] ?? 0,
                        'tax_code' => $taxLine['tax_code'] ?? 'S',
                        'cgst_rate' => $taxLine['cgst_rate'] ?? 0,
                        'cgst_amount' => $taxLine['cgst_amount'] ?? 0,
                        'sgst_rate' => $taxLine['sgst_rate'] ?? 0,
                        'sgst_amount' => $taxLine['sgst_amount'] ?? 0,
                        'igst_rate' => $taxLine['igst_rate'] ?? 0,
                        'igst_amount' => $taxLine['igst_amount'] ?? 0,
                        'line_order' => $index,
                    ]));
                }

                $bill->recalculateTotals();
            }

            return $bill->fresh(['lines', 'supplier']);
        });
    }

    /**
     * Approve a bill.
     */
    public function approve(Bill $bill, int $userId): Bill
    {
        if (!in_array($bill->status, [Bill::STATUS_DRAFT, Bill::STATUS_PENDING], true)) {
            throw new \InvalidArgumentException('Only draft/pending bills can be approved.');
        }

        // --- 3-way match enforcement (SAP-style AP enforcement) ---
        // Run the match INSIDE the approval transaction so that match results and the
        // bill status update are committed atomically. If approval fails after the match,
        // the match results roll back with the transaction and no orphaned records remain.
        return DB::transaction(function () use ($bill, $userId) {
            // Re-load the bill with a lock to prevent concurrent approvals
            $bill = Bill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if (!in_array($bill->status, [Bill::STATUS_DRAFT, Bill::STATUS_PENDING], true)) {
                throw new \InvalidArgumentException('Only draft/pending bills can be approved.');
            }

            if ($bill->purchase_order_id) {
                $purchaseOrder = PurchaseOrder::find($bill->purchase_order_id);
                if ($purchaseOrder && in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_CANCELLED], true)) {
                    throw new \RuntimeException('Cannot approve bill: related purchase order is not approved.');
                }
            }

            if ($bill->purchase_order_id) {
                $matchResult = $this->goodsReceiptService->runThreeWayMatch($bill);

                if (!($matchResult['all_matched'] ?? false)) {
                    $summary = $matchResult['summary'] ?? [];
                    throw ApiException::fromError(
                        ErrorCodes::PURCH_THREE_WAY_MATCH_FAILED,
                        [
                            'bill_id'   => $bill->id,
                            'summary'   => $summary,
                            'results'   => collect($matchResult['results'] ?? [])
                                ->where('match_status', '!=', 'matched')
                                ->values()
                                ->map(fn($r) => [
                                    'bill_line_id'    => $r->bill_line_id,
                                    'match_status'    => $r->match_status,
                                    'variance_amount' => $r->variance_amount,
                                ])
                                ->all(),
                        ]
                    );
                }
            }

            if (bccomp((string) $bill->total, '0', 4) <= 0) {
                throw new \InvalidArgumentException('Bill total must be positive before approval.');
            }

            $journal = $this->createJournalEntry($bill);
            $journalId = $journal?->id;

            // Only add inventory when no posted Goods Receipt already exists for
            // this Purchase Order. If a GR was posted, stock was already incremented
            // there; adding it again here would double-count the inventory.
            // Also guard against the PurchaseOrderService::receive() path, which
            // writes stock movements directly without creating a GoodsReceipt record.
            $hasPostedGr = false;
            $hasDirectReceive = false;
            if ($bill->purchase_order_id) {
                $hasPostedGr = \App\Models\Purchase\GoodsReceipt::where('purchase_order_id', $bill->purchase_order_id)
                    ->where('status', \App\Models\Purchase\GoodsReceipt::STATUS_POSTED)
                    ->exists();

                // Check if stock was added via PurchaseOrderService::receive()
                $hasDirectReceive = \App\Models\Inventory\StockMovement::where('reference_type', 'purchase_order')
                    ->where('reference_id', $bill->purchase_order_id)
                    ->where('movement_type', 'purchase')
                    ->exists();
            }

            // Idempotency guard: skip inventory if a stock movement for this bill already exists
            $billInventoryAlreadyAdded = \App\Models\Inventory\StockMovement::where('reference_type', 'bill')
                ->where('reference_id', $bill->id)
                ->exists();

            if (!$hasPostedGr && !$hasDirectReceive && !$billInventoryAlreadyAdded) {
                $this->addInventory($bill);
            }

            $bill->update([
                'status' => Bill::STATUS_APPROVED,
                'journal_entry_id' => $journalId,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $bill->fresh();
        });
    }

    /**
     * Void a bill.
     */
    public function void(Bill $bill, string $reason = ''): Bill
    {
        if ($bill->isPaid()) {
            throw new \InvalidArgumentException('Paid bills cannot be voided. Create a debit note instead.');
        }

        if ($bill->status === Bill::STATUS_VOIDED) {
            throw new \InvalidArgumentException('Bill is already voided.');
        }

        if ($bill->status === Bill::STATUS_PARTIAL) {
            throw new \InvalidArgumentException('Partially-paid bills cannot be voided. Reverse existing payments first.');
        }

        return DB::transaction(function () use ($bill, $reason) {
            // Re-fetch with a pessimistic lock to prevent concurrent void operations
            $bill = Bill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if ($bill->status === Bill::STATUS_VOIDED) {
                throw new \InvalidArgumentException('Bill is already voided.');
            }

            // Prevent voiding if a posted goods receipt exists for the related PO
            if ($bill->purchase_order_id) {
                $hasPostedGrForVoid = \App\Models\Purchase\GoodsReceipt::where('purchase_order_id', $bill->purchase_order_id)
                    ->where('status', \App\Models\Purchase\GoodsReceipt::STATUS_POSTED)
                    ->exists();

                if ($hasPostedGrForVoid) {
                    throw new \RuntimeException('Cannot void bill with a posted goods receipt.');
                }
            }

            if ($bill->journal_entry_id && ($journalEntry = $bill->journalEntry)) {
                $this->journalService->void($journalEntry, $reason);
            }

            // Mirror the approve() guard: only reverse inventory if stock was
            // originally added by the bill approval path. When a GR or a direct
            // PurchaseOrderService::receive() added stock, reversal must go through
            // those respective flows — not through the bill void.
            $hasPostedGr = false;
            $hasDirectReceive = false;
            if ($bill->purchase_order_id) {
                $hasPostedGr = \App\Models\Purchase\GoodsReceipt::where('purchase_order_id', $bill->purchase_order_id)
                    ->where('status', \App\Models\Purchase\GoodsReceipt::STATUS_POSTED)
                    ->exists();

                $hasDirectReceive = \App\Models\Inventory\StockMovement::where('reference_type', 'purchase_order')
                    ->where('reference_id', $bill->purchase_order_id)
                    ->where('movement_type', 'purchase')
                    ->exists();
            }

            if (!$hasPostedGr && !$hasDirectReceive) {
                $this->reverseInventory($bill);
            }

            $bill->update([
                'status' => Bill::STATUS_VOIDED,
                'notes' => $bill->notes . "\n\nVoided: " . $reason,
            ]);

            return $bill->fresh();
        });
    }

    /**
     * Create bill from purchase order.
     */
    public function createFromPurchaseOrder(PurchaseOrder $order, ?array $lineQuantities = null): Bill
    {
        if (!$order->canBeBilled()) {
            throw new \InvalidArgumentException('Purchase order cannot be billed in current status.');
        }

        $lines = $order->lines
            ->filter(fn($line) => $line->getRemainingToBill() > 0)
            ->map(function ($line) use ($lineQuantities) {
                $quantity = $lineQuantities[$line->id] ?? $line->getRemainingToBill();

                return [
                    'product_id' => $line->product_id,
                    'variant_id' => $line->variant_id,
                    'description' => $line->description,
                    'quantity' => $quantity,
                    'unit_id' => $line->unit_id,
                    'unit_price' => $line->unit_price,
                    'discount_type' => $line->discount_type,
                    'discount_value' => $line->discount_value,
                    'tax_category_id' => $line->tax_category_id,
                    'warehouse_id' => $line->warehouse_id,
                ];
            })->toArray();

        if (empty($lines)) {
            throw new \InvalidArgumentException('No items available to bill.');
        }

        return DB::transaction(function () use ($order, $lines) {
            $bill = $this->create([
                'supplier_id' => $order->supplier_id,
                'purchase_order_id' => $order->id,
                'bill_date' => now(),
                'branch_id' => $order->branch_id,
                'currency_code' => $order->currency_code,
                'exchange_rate' => $order->exchange_rate,
                'discount_type' => $order->discount_type,
                'discount_value' => $order->discount_value,
                'notes' => $order->notes,
                'reference' => $order->order_number,
            ], $lines);

            foreach ($bill->lines as $billLine) {
                if ($billLine->product_id) {
                    $orderLine = $order->lines()
                        ->where('product_id', $billLine->product_id)
                        ->where('variant_id', $billLine->variant_id)
                        ->first();

                    if ($orderLine) {
                        $orderLine->increment('quantity_billed', $billLine->quantity);
                    }
                }
            }

            $progress = $order->fresh()->getReceivingProgress();
            if ($progress['billing_percentage'] >= 100) {
                $order->update(['status' => PurchaseOrder::STATUS_BILLED]);
            }

            return $bill;
        });
    }

    /**
     * Create journal entry for bill.
     */
    protected function createJournalEntry(Bill $bill): ?\App\Models\Accounting\JournalEntry
    {
        return $this->journalEntryFactory->forBill($bill);
    }

    /**
     * Add inventory for bill lines.
     */
    protected function addInventory(Bill $bill): void
    {
        foreach ($bill->lines as $line) {
            if ($line->product_id && $line->product?->track_inventory && $line->warehouse_id) {
                $this->stockService->recordPurchase(
                    productId: $line->product_id,
                    warehouseId: $line->warehouse_id,
                    quantity: $line->quantity,
                    unitCost: $line->unit_price,
                    variantId: $line->variant_id,
                    referenceNumber: $bill->bill_number,
                    referenceId: $bill->id
                );
            }
        }
    }

    /**
     * Reverse inventory for voided bill.
     */
    protected function reverseInventory(Bill $bill): void
    {
        foreach ($bill->lines as $line) {
            if ($line->product_id && $line->product?->track_inventory && $line->warehouse_id) {
                $this->stockService->recordMovement(
                    productId: $line->product_id,
                    warehouseId: $line->warehouse_id,
                    movementType: 'return_out',
                    direction: 'out',
                    quantity: $line->quantity,
                    unitCost: $line->unit_price,
                    variantId: $line->variant_id,
                    referenceType: 'bill',
                    referenceId: $bill->id,
                    referenceNumber: $bill->bill_number . '-VOID',
                    notes: 'Inventory reversed - bill voided'
                );
            }
        }
    }

    /**
     * Transition all past-due approved/partial bills to overdue status.
     * Returns the number of bills updated.
     */
    public function markOverdueBills(): int
    {
        return Bill::whereIn('status', [Bill::STATUS_APPROVED, Bill::STATUS_PARTIAL])
            ->where('due_date', '<', now())
            ->update(['status' => Bill::STATUS_OVERDUE]);
    }
}
