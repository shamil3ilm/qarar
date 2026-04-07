<?php

declare(strict_types=1);

namespace App\Commands\Sales;

use App\Commands\Contracts\Command;

final readonly class CreateInvoiceCommand implements Command
{
    public function __construct(
        public readonly int     $organizationId,
        public readonly int     $customerId,
        public readonly string  $invoiceDate,
        public readonly array   $lines,
        public readonly string  $currencyCode = 'SAR',
        public readonly ?string $notes = null,
        public readonly ?string $referenceNumber = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            organizationId:  (int) $data['organization_id'],
            customerId:      (int) $data['customer_id'],
            invoiceDate:     $data['invoice_date'],
            lines:           $data['lines'],
            currencyCode:    $data['currency_code'] ?? 'SAR',
            notes:           $data['notes'] ?? null,
            referenceNumber: $data['reference_number'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id'  => $this->organizationId,
            'customer_id'      => $this->customerId,
            'invoice_date'     => $this->invoiceDate,
            'lines'            => $this->lines,
            'currency_code'    => $this->currencyCode,
            'notes'            => $this->notes,
            'reference_number' => $this->referenceNumber,
        ];
    }
}
