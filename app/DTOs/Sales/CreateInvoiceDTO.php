<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

use App\DTOs\Contracts\DataTransferObject;

final readonly class CreateInvoiceDTO implements DataTransferObject
{
    /** @param list<InvoiceLineDTO> $lines */
    public function __construct(
        public int     $organizationId,
        public int     $customerId,
        public string  $invoiceDate,
        public array   $lines,
        public string  $currencyCode    = 'SAR',
        public ?string $dueDate         = null,
        public ?string $notes           = null,
        public ?string $referenceNumber = null,
        public ?int    $branchId        = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            organizationId:  (int) $data['organization_id'],
            customerId:      (int) $data['customer_id'],
            invoiceDate:     $data['invoice_date'],
            lines:           array_map(
                fn(array $line) => InvoiceLineDTO::fromArray($line),
                $data['lines'] ?? []
            ),
            currencyCode:    $data['currency_code'] ?? 'SAR',
            dueDate:         $data['due_date'] ?? null,
            notes:           $data['notes'] ?? null,
            referenceNumber: $data['reference_number'] ?? null,
            branchId:        isset($data['branch_id']) ? (int) $data['branch_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id'  => $this->organizationId,
            'customer_id'      => $this->customerId,
            'invoice_date'     => $this->invoiceDate,
            'lines'            => array_map(fn(InvoiceLineDTO $l) => $l->toArray(), $this->lines),
            'currency_code'    => $this->currencyCode,
            'due_date'         => $this->dueDate,
            'notes'            => $this->notes,
            'reference_number' => $this->referenceNumber,
            'branch_id'        => $this->branchId,
        ];
    }

    public function subtotal(): float
    {
        return array_sum(array_map(fn(InvoiceLineDTO $l) => $l->lineTotal(), $this->lines));
    }

    public function totalTax(): float
    {
        return array_sum(array_map(fn(InvoiceLineDTO $l) => $l->taxAmount(), $this->lines));
    }

    public function total(): float
    {
        return $this->subtotal() + $this->totalTax();
    }
}
