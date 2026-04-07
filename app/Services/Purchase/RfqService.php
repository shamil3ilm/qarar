<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\RfqHeader;
use App\Models\Purchase\RfqQuote;
use App\Models\Purchase\RfqVendor;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

class RfqService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * Create a new RFQ with line items.
     */
    public function createRfq(array $data): RfqHeader
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['rfq_number'])) {
                $data['rfq_number'] = $this->numberGenerator->generate('RFQ');
            }

            $items = $data['items'] ?? [];
            unset($data['items']);

            $data['created_by'] = $data['created_by'] ?? auth()->id();
            $data['status'] = RfqHeader::STATUS_DRAFT;

            $rfq = RfqHeader::create($data);

            foreach ($items as $index => $itemData) {
                $itemData['sort_order'] = $itemData['sort_order'] ?? $index;
                $rfq->items()->create($itemData);
            }

            return $rfq->load(['items']);
        });
    }

    /**
     * Send RFQ to a list of vendor contact IDs.
     *
     * @param  int[]  $vendorContactIds
     */
    public function sendToVendors(RfqHeader $rfq, array $vendorContactIds): RfqHeader
    {
        if (!$rfq->canBeSent() && $rfq->status !== RfqHeader::STATUS_SENT) {
            throw new \InvalidArgumentException('RFQ cannot be sent in its current status.');
        }

        return DB::transaction(function () use ($rfq, $vendorContactIds) {
            foreach ($vendorContactIds as $contactId) {
                $rfq->vendors()->firstOrCreate(
                    ['contact_id' => $contactId],
                    [
                        'status' => 'invited',
                        'sent_at' => now(),
                        'response_deadline' => $rfq->submission_deadline,
                    ]
                );
            }

            // Mark already-existing unsent vendors as sent
            $rfq->vendors()
                ->whereIn('contact_id', $vendorContactIds)
                ->whereNull('sent_at')
                ->update(['sent_at' => now(), 'status' => 'invited']);

            $rfq->update(['status' => RfqHeader::STATUS_SENT]);

            return $rfq->fresh(['items', 'vendors.contact']);
        });
    }

    /**
     * Record a vendor's quote against an RFQ vendor invitation.
     */
    public function recordQuote(RfqVendor $rfqVendor, array $quoteData): RfqQuote
    {
        if ($rfqVendor->status === 'declined') {
            throw new \InvalidArgumentException('Cannot record a quote for a vendor who declined.');
        }

        return DB::transaction(function () use ($rfqVendor, $quoteData) {
            $lines = $quoteData['lines'] ?? [];
            unset($quoteData['lines']);

            $quoteData['rfq_id'] = $rfqVendor->rfq_id;
            $quoteData['rfq_vendor_id'] = $rfqVendor->id;
            $quoteData['contact_id'] = $rfqVendor->contact_id;
            $quoteData['status'] = 'received';

            // Calculate total from lines if not provided
            if (empty($quoteData['total_amount']) && !empty($lines)) {
                $total = '0';
                foreach ($lines as $line) {
                    $total = bcadd($total, (string) ($line['line_total'] ?? 0), 4);
                }
                $quoteData['total_amount'] = $total;
            }

            $quote = RfqQuote::create($quoteData);

            foreach ($lines as $lineData) {
                $quote->lines()->create($lineData);
            }

            $rfqVendor->update(['status' => 'responded']);

            return $quote->load(['lines']);
        });
    }

    /**
     * Award an RFQ to the winning vendor quote.
     */
    public function awardQuote(RfqQuote $quote): RfqQuote
    {
        if (!$quote->canBeAwarded()) {
            throw new \InvalidArgumentException('Quote cannot be awarded in its current status.');
        }

        return DB::transaction(function () use ($quote) {
            // Reject all other quotes for this RFQ
            RfqQuote::where('rfq_id', $quote->rfq_id)
                ->where('id', '!=', $quote->id)
                ->whereIn('status', ['received', 'evaluated'])
                ->update(['status' => 'rejected']);

            // Reject all other vendors
            RfqVendor::where('rfq_id', $quote->rfq_id)
                ->where('id', '!=', $quote->rfq_vendor_id)
                ->whereIn('status', ['invited', 'responded'])
                ->update(['status' => 'rejected']);

            $quote->update(['status' => 'awarded']);
            $quote->rfqVendor()->update(['status' => 'awarded']);
            $quote->rfq()->update(['status' => RfqHeader::STATUS_AWARDED]);

            return $quote->fresh(['rfqVendor', 'rfq']);
        });
    }

    /**
     * Convert an awarded quote to a Purchase Order.
     */
    public function convertToPurchaseOrder(RfqQuote $quote): PurchaseOrder
    {
        if (!$quote->isAwarded()) {
            throw new \InvalidArgumentException('Only awarded quotes can be converted to a purchase order.');
        }

        return DB::transaction(function () use ($quote) {
            $quote->load(['lines.rfqItem', 'rfq']);

            $lines = $quote->lines->map(function ($quoteLine) {
                $rfqItem = $quoteLine->rfqItem;

                return [
                    'product_id'     => $rfqItem?->product_id,
                    'variant_id'     => $rfqItem?->variant_id ?? null,
                    'description'    => $rfqItem?->description ?? '',
                    'quantity'       => $quoteLine->quantity,
                    'unit_id'        => $rfqItem?->unit_id,
                    'unit_price'     => $quoteLine->unit_price,
                    'discount_type'  => 'percentage',
                    'discount_value' => $quoteLine->discount_pct,
                    'tax_rate'       => $quoteLine->tax_rate,
                ];
            })->toArray();

            $poData = [
                'supplier_id'            => $quote->contact_id,
                'order_date'             => now()->toDateString(),
                'expected_delivery_date' => $quote->rfq->delivery_date?->toDateString(),
                'delivery_address'       => $quote->rfq->delivery_address,
                'currency_code'          => $quote->currency_code,
                'notes'                  => "Created from RFQ {$quote->rfq->rfq_number}. Vendor quote: {$quote->quote_number}",
                'reference'              => $quote->rfq->rfq_number,
            ];

            $purchaseOrder = $this->purchaseOrderService->create($poData, $lines);

            // Mark the RFQ as closed now that it has been converted to a PO.
            $quote->rfq()->update(['status' => RfqHeader::STATUS_CLOSED]);

            return $purchaseOrder;
        });
    }

    /**
     * Build a comparison matrix of all quotes for an RFQ.
     */
    public function compareQuotes(RfqHeader $rfq): array
    {
        $rfq->load(['items', 'quotes.lines.rfqItem', 'quotes.contact']);

        $quotes = $rfq->quotes->whereIn('status', ['received', 'evaluated', 'awarded']);

        $matrix = [
            'rfq_id' => $rfq->id,
            'rfq_number' => $rfq->rfq_number,
            'items' => $rfq->items->map(fn($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
            ])->values()->toArray(),
            'quotes' => $quotes->map(function ($quote) use ($rfq) {
                $linesByItem = $quote->lines->keyBy('rfq_item_id');

                return [
                    'quote_id' => $quote->id,
                    'vendor_name' => $quote->contact?->getDisplayName(),
                    'quote_number' => $quote->quote_number,
                    'currency_code' => $quote->currency_code,
                    'total_amount' => (float) $quote->total_amount,
                    'delivery_days' => $quote->delivery_days,
                    'payment_terms' => $quote->payment_terms,
                    'valid_until' => $quote->valid_until?->toDateString(),
                    'status' => $quote->status,
                    'line_prices' => $rfq->items->map(function ($item) use ($linesByItem) {
                        $line = $linesByItem->get($item->id);

                        return [
                            'rfq_item_id' => $item->id,
                            'unit_price' => $line ? (float) $line->unit_price : null,
                            'line_total' => $line ? (float) $line->line_total : null,
                            'delivery_days' => $line?->delivery_days,
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray(),
        ];

        // Identify lowest price per item
        foreach ($matrix['items'] as $itemIndex => $item) {
            $lowestPrice = null;
            $lowestQuoteId = null;

            foreach ($matrix['quotes'] as $quoteData) {
                $linePrice = $quoteData['line_prices'][$itemIndex]['unit_price'] ?? null;

                if ($linePrice !== null && ($lowestPrice === null || $linePrice < $lowestPrice)) {
                    $lowestPrice = $linePrice;
                    $lowestQuoteId = $quoteData['quote_id'];
                }
            }

            $matrix['items'][$itemIndex]['lowest_price'] = $lowestPrice;
            $matrix['items'][$itemIndex]['lowest_quote_id'] = $lowestQuoteId;
        }

        return $matrix;
    }
}
