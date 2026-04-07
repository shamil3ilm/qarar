<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Accounting\Account;
use App\Models\Sales\IcBillingDocument;
use App\Models\Sales\IcPurchaseOrderLink;
use App\Models\Sales\IntercompanySalesOrder;
use App\Models\Sales\IntercompanySalesOrderLine;
use App\Services\Accounting\JournalService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IntercompanySalesService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    /**
     * List intercompany sales orders with optional filters.
     *
     * @param  array{selling_organization_id?:int, buying_organization_id?:int, status?:string}  $filters
     */
    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = IntercompanySalesOrder::query()
            ->with(['sellingOrganization', 'buyingOrganization', 'createdBy'])
            ->latest('order_date');

        if (!empty($filters['selling_organization_id'])) {
            $query->scopeForSellingOrg($query, (int) $filters['selling_organization_id']);
        }

        if (!empty($filters['buying_organization_id'])) {
            $query->scopeForBuyingOrg($query, (int) $filters['buying_organization_id']);
        }

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create an intercompany sales order with lines and a pending PO link.
     *
     * @param  array{
     *     selling_organization_id: int,
     *     buying_organization_id: int,
     *     order_number: string,
     *     order_date: string,
     *     currency_code?: string,
     *     requested_delivery_date?: string|null,
     *     transfer_price_version_id?: int|null,
     *     notes?: string|null,
     *     created_by?: int|null,
     *     lines: array<int, array{
     *         product_id: int,
     *         line_number: int,
     *         description?: string|null,
     *         quantity: float|string,
     *         unit_of_measure?: string|null,
     *         transfer_price: float|string,
     *         list_price?: float|string|null,
     *         tax_rate?: float|string,
     *     }>
     * }  $data
     */
    public function create(array $data): IntercompanySalesOrder
    {
        return DB::transaction(function () use ($data): IntercompanySalesOrder {
            $linesData = $data['lines'] ?? [];
            unset($data['lines']);

            /** @var IntercompanySalesOrder $order */
            $order = IntercompanySalesOrder::create($data);

            foreach ($linesData as $lineData) {
                $quantity      = (string) $lineData['quantity'];
                $transferPrice = (string) $lineData['transfer_price'];
                $taxRate       = (string) ($lineData['tax_rate'] ?? '0');
                $lineTotal     = bcmul($quantity, $transferPrice, 4);
                $taxAmount     = bcmul($lineTotal, bcdiv($taxRate, '100', 6), 4);

                IntercompanySalesOrderLine::create(array_merge($lineData, [
                    'intercompany_sales_order_id' => $order->id,
                    'line_total'                  => $lineTotal,
                    'tax_amount'                  => $taxAmount,
                ]));
            }

            $order->recalculateTotals();

            IcPurchaseOrderLink::create([
                'intercompany_sales_order_id' => $order->id,
                'buying_organization_id'      => $order->buying_organization_id,
                'purchase_order_id'           => null,
                'status'                      => 'pending',
            ]);

            return $order->fresh(['lines', 'purchaseOrderLink']);
        });
    }

    /**
     * Update header fields (not lines) of an existing order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(IntercompanySalesOrder $order, array $data): IntercompanySalesOrder
    {
        unset($data['lines']);
        $order->update($data);

        return $order->fresh();
    }

    /**
     * Transition a draft order to confirmed.
     */
    public function confirm(IntercompanySalesOrder $order): IntercompanySalesOrder
    {
        if (!$order->canConfirm()) {
            throw new \RuntimeException("Order [{$order->order_number}] cannot be confirmed from status [{$order->status}].");
        }

        $order->update(['status' => IntercompanySalesOrder::STATUS_CONFIRMED]);

        return $order->fresh();
    }

    /**
     * Link the buying org's purchase order to this IC sales order.
     */
    public function linkPurchaseOrder(IntercompanySalesOrder $order, int $purchaseOrderId): IcPurchaseOrderLink
    {
        $link = $order->purchaseOrderLink ?? IcPurchaseOrderLink::firstOrNew([
            'intercompany_sales_order_id' => $order->id,
        ]);

        $link->fill([
            'purchase_order_id'      => $purchaseOrderId,
            'buying_organization_id' => $order->buying_organization_id,
            'status'                 => 'linked',
        ])->save();

        return $link->fresh();
    }

    /**
     * Transition a confirmed order to in_delivery.
     */
    public function startDelivery(IntercompanySalesOrder $order): IntercompanySalesOrder
    {
        if ($order->status !== IntercompanySalesOrder::STATUS_CONFIRMED) {
            throw new \RuntimeException("Order [{$order->order_number}] must be confirmed before starting delivery.");
        }

        $order->update(['status' => IntercompanySalesOrder::STATUS_IN_DELIVERY]);

        return $order->fresh();
    }

    /**
     * Create a draft intercompany billing document (SAP IV document type).
     *
     * @param  array{
     *     document_number: string,
     *     billing_date: string,
     *     currency_code?: string,
     *     subtotal: float|string,
     *     tax_amount?: float|string,
     *     total_amount: float|string,
     *     notes?: string|null,
     * }  $data
     */
    public function createBillingDocument(IntercompanySalesOrder $order, array $data): IcBillingDocument
    {
        if (!$order->canBill()) {
            throw new \RuntimeException("Order [{$order->order_number}] is not in a billable status.");
        }

        return IcBillingDocument::create(array_merge($data, [
            'intercompany_sales_order_id' => $order->id,
            'selling_organization_id'     => $order->selling_organization_id,
            'buying_organization_id'      => $order->buying_organization_id,
            'status'                      => IcBillingDocument::STATUS_DRAFT,
        ]));
    }

    /**
     * Post a draft billing document (set status = posted, record posted_at timestamp).
     */
    public function postBillingDocument(IcBillingDocument $doc): IcBillingDocument
    {
        if (!$doc->canPost()) {
            throw new \RuntimeException("Billing document [{$doc->document_number}] cannot be posted from status [{$doc->status}].");
        }

        return DB::transaction(function () use ($doc): IcBillingDocument {
            $doc->update([
                'status'    => IcBillingDocument::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            // Auto-post AR in the selling org and AP in the buying org
            $journalEntryId = $this->postIcJournalEntries($doc);

            if ($journalEntryId) {
                $doc->update(['journal_entry_id' => $journalEntryId]);
            }

            // Transition the parent order to billed when at least one document is posted
            $order = $doc->intercompanySalesOrder;
            if ($order && $order->status !== IntercompanySalesOrder::STATUS_BILLED) {
                $order->update(['status' => IntercompanySalesOrder::STATUS_BILLED]);
            }

            return $doc->fresh(['journalEntry']);
        });
    }

    /**
     * Auto-create AR (selling org) and AP (buying org) journal entries on billing document post.
     * Returns the AR journal entry ID, or null if required accounts cannot be resolved.
     */
    private function postIcJournalEntries(IcBillingDocument $doc): ?int
    {
        $sellingOrgId = $doc->selling_organization_id;
        $buyingOrgId  = $doc->buying_organization_id;
        $amount       = (float) $doc->total_amount;
        $ref          = $doc->document_number;
        $description  = "IC billing: {$ref}";

        // Resolve AR account for the selling organization (first active receivable account)
        $arAccount = Account::where('organization_id', $sellingOrgId)
            ->where('account_subtype', Account::SUBTYPE_RECEIVABLE)
            ->where('is_active', true)
            ->first();

        // Resolve income (IC revenue) account for the selling organization
        $revenueAccount = Account::where('organization_id', $sellingOrgId)
            ->where('account_type', Account::TYPE_INCOME)
            ->where('is_active', true)
            ->first();

        // Resolve AP account for the buying organization (first active payable account)
        $apAccount = Account::where('organization_id', $buyingOrgId)
            ->where('account_subtype', Account::SUBTYPE_PAYABLE)
            ->where('is_active', true)
            ->first();

        // Resolve expense account for the buying organization
        $expenseAccount = Account::where('organization_id', $buyingOrgId)
            ->where('account_type', Account::TYPE_EXPENSE)
            ->where('is_active', true)
            ->first();

        if (!$arAccount || !$revenueAccount || !$apAccount || !$expenseAccount) {
            // Cannot auto-post without GL accounts — skip silently
            return null;
        }

        // Selling org: DR AR / CR IC Revenue
        $arEntry = $this->journalService->createSimpleEntry(
            organizationId: $sellingOrgId,
            branchId: 0,
            debitAccountId: $arAccount->id,
            creditAccountId: $revenueAccount->id,
            amount: $amount,
            description: $description,
            reference: $ref,
            date: $doc->billing_date?->toDateString() ?? now()->toDateString(),
        );
        $this->journalService->postEntry($arEntry);

        // Buying org: DR IC Expense / CR AP
        $apEntry = $this->journalService->createSimpleEntry(
            organizationId: $buyingOrgId,
            branchId: 0,
            debitAccountId: $expenseAccount->id,
            creditAccountId: $apAccount->id,
            amount: $amount,
            description: $description,
            reference: $ref,
            date: $doc->billing_date?->toDateString() ?? now()->toDateString(),
        );
        $this->journalService->postEntry($apEntry);

        return $arEntry->id;
    }

    /**
     * Cancel a draft or confirmed intercompany sales order.
     */
    public function cancel(IntercompanySalesOrder $order): IntercompanySalesOrder
    {
        if (!$order->canCancel()) {
            throw new \RuntimeException("Order [{$order->order_number}] cannot be cancelled from status [{$order->status}].");
        }

        DB::transaction(function () use ($order): void {
            $order->update(['status' => IntercompanySalesOrder::STATUS_CANCELLED]);

            if ($link = $order->purchaseOrderLink) {
                $link->update(['status' => 'cancelled']);
            }
        });

        return $order->fresh();
    }
}
