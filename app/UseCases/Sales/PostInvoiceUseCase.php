<?php

declare(strict_types=1);

namespace App\UseCases\Sales;

use App\Http\Resources\Sales\InvoiceResource;
use App\Models\Sales\Invoice;
use App\Services\Sales\InvoiceService;
use App\UseCases\Contracts\UseCase;

class PostInvoiceUseCase implements UseCase
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Post (send) an invoice.
     *
     * Required key: invoice_id
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public function handle(array $data): array
    {
        if (! array_key_exists('invoice_id', $data)) {
            throw new \InvalidArgumentException('Missing required key: invoice_id');
        }

        $invoice = Invoice::findOrFail($data['invoice_id']);

        $posted = $this->invoiceService->send($invoice);

        return (new InvoiceResource($posted))->toArray(request());
    }
}
