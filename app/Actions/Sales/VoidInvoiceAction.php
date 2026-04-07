<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Actions\Contracts\Action;
use App\Models\Sales\Invoice;
use App\Services\Sales\InvoiceService;
use InvalidArgumentException;

class VoidInvoiceAction implements Action
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(array $payload): Invoice
    {
        if (empty($payload['invoice_id'])) {
            throw new InvalidArgumentException('invoice_id is required.');
        }

        $invoice = Invoice::findOrFail($payload['invoice_id']);
        $reason  = $payload['reason'] ?? null;

        return $this->invoiceService->void($invoice, $reason ?? '');
    }
}
