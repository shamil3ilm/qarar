<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Events\Sales\InvoicePosted;
use App\Models\Accounting\FiscalYear;
use App\Services\Accounting\CopaService;
use Illuminate\Contracts\Queue\ShouldQueue;

class PostCopaOnInvoicePostedListener implements ShouldQueue
{
    public function __construct(
        private readonly CopaService $copaService
    ) {}

    /**
     * Auto-post a CO-PA line item when a sales invoice is posted.
     *
     * Maps invoice totals to CO-PA: revenue = subtotal, cogs = 0 (updated via inventory
     * cost of goods sold posting), overhead_allocated = 0 (updated by assessment cycle).
     */
    public function handle(InvoicePosted $event): void
    {
        $invoice = $event->invoice->loadMissing(['lines', 'customer']);

        $revenue     = (float) ($invoice->subtotal ?? 0);
        $taxAmount   = (float) ($invoice->tax_amount ?? 0);
        $grossProfit = $revenue; // COGS will be updated when goods issue is processed

        // Derive period from invoice date
        $invoiceDate   = $invoice->invoice_date ?? now();
        $period        = (int) $invoiceDate->format('n');
        $fiscalYearInt = (int) $invoiceDate->format('Y');

        // Resolve fiscal year record — skip if not found
        $fiscalYearRecord = FiscalYear::withoutGlobalScopes()
            ->where('organization_id', $invoice->organization_id)
            ->whereYear('start_date', $fiscalYearInt)
            ->first();

        if ($fiscalYearRecord === null) {
            return;
        }

        // Group by product so each product gets its own COPA line item
        $linesByProduct = $invoice->lines->groupBy('product_id');

        foreach ($linesByProduct as $productId => $lines) {
            $lineRevenue = $lines->sum('subtotal');

            $this->copaService->recordLineItem([
                'organization_id'  => $invoice->organization_id,
                'fiscal_year_id'   => $fiscalYearRecord->id,
                'period'           => $period,
                'posting_date'     => $invoiceDate->toDateString(),
                'source_document_type' => 'invoice',
                'source_document_id'   => $invoice->id,
                'product_id'       => $productId ?: null,
                'contact_id'       => $invoice->customer_id ?? null,
                'revenue'          => (float) $lineRevenue,
                'cogs'             => 0.0,
                'gross_profit'     => (float) $lineRevenue,
                'overhead_allocated' => 0.0,
                'net_profit'       => (float) $lineRevenue,
            ]);
        }
    }
}
