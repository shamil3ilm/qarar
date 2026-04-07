<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Actions\Contracts\Action;
use App\Models\Sales\Invoice;
use App\Services\Sales\InvoiceService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateInvoiceAction implements Action
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(array $payload): Invoice
    {
        if (empty($payload['customer_id'])) {
            throw new InvalidArgumentException('customer_id is required.');
        }

        if (empty($payload['invoice_date'])) {
            throw new InvalidArgumentException('invoice_date is required.');
        }

        if (empty($payload['lines']) || !is_array($payload['lines']) || count($payload['lines']) < 1) {
            throw new InvalidArgumentException('lines must be a non-empty array.');
        }

        $lines = $payload['lines'];

        return DB::transaction(function () use ($payload, $lines): Invoice {
            return $this->invoiceService->create($payload, $lines);
        });
    }
}
