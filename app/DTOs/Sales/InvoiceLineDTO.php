<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

use App\DTOs\Contracts\DataTransferObject;

final readonly class InvoiceLineDTO implements DataTransferObject
{
    public function __construct(
        public int     $productId,
        public float   $quantity,
        public float   $unitPrice,
        public float   $taxRate,
        public ?string $description    = null,
        public ?int    $warehouseId    = null,
        public float   $discountAmount = 0.0,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            productId:      (int)   $data['product_id'],
            quantity:       (float) $data['quantity'],
            unitPrice:      (float) $data['unit_price'],
            taxRate:        (float) ($data['tax_rate'] ?? 0.0),
            description:    $data['description'] ?? null,
            warehouseId:    isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
            discountAmount: (float) ($data['discount_amount'] ?? 0.0),
        );
    }

    public function toArray(): array
    {
        return [
            'product_id'      => $this->productId,
            'quantity'        => $this->quantity,
            'unit_price'      => $this->unitPrice,
            'tax_rate'        => $this->taxRate,
            'description'     => $this->description,
            'warehouse_id'    => $this->warehouseId,
            'discount_amount' => $this->discountAmount,
        ];
    }

    public function lineTotal(): float
    {
        return round($this->quantity * $this->unitPrice - $this->discountAmount, 4);
    }

    public function taxAmount(): float
    {
        return round($this->lineTotal() * $this->taxRate, 4);
    }
}
