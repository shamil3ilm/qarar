<?php

declare(strict_types=1);

namespace App\UseCases\Sales;

use App\Http\Resources\Sales\InvoiceResource;
use App\Services\Sales\InvoiceService;
use App\Services\Tax\TaxCalculatorService;
use App\UseCases\Contracts\UseCase;
use Illuminate\Support\Facades\DB;

class CreateInvoiceUseCase implements UseCase
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TaxCalculatorService $taxCalculatorService,
    ) {}

    /**
     * Create a new invoice.
     *
     * Required keys: customer_id, invoice_date, lines
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public function handle(array $data): array
    {
        foreach (['customer_id', 'invoice_date', 'lines'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new \InvalidArgumentException("Missing required key: {$key}");
            }
        }

        $lines = $data['lines'];
        unset($data['lines']);

        $invoice = DB::transaction(
            fn () => $this->invoiceService->create($data, $lines)
        );

        return (new InvoiceResource($invoice))->toArray(request());
    }
}
