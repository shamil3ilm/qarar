<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Sales\Invoice;

/**
 * Transforms ERP Invoice models into the ZATCA API payload format.
 */
class ZatcaInvoiceTransformer
{
    /**
     * Transform an ERP Invoice into the ZATCA submission payload.
     *
     * @return array<string, mixed>
     */
    public static function transform(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines', 'customer', 'originalInvoice', 'organization']);

        [$type, $documentType] = self::mapInvoiceType($invoice->invoice_type);

        $payload = [
            'invoice_number' => $invoice->invoice_number,
            'type' => $type,
            'document_type' => $documentType,
            'issue_date' => $invoice->invoice_date->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'currency' => $invoice->currency_code ?? 'SAR',
            'buyer_name' => $invoice->customer_name,
            'buyer_vat_number' => $invoice->customer_tax_number,
            'buyer_address' => self::buildBuyerAddress($invoice),
            'lines' => self::transformLines($invoice),
            'totals' => [
                'subtotal' => (float) $invoice->subtotal,
                'discount' => (float) $invoice->discount_amount,
                'tax' => (float) $invoice->tax_amount,
                'total' => (float) $invoice->total,
            ],
            'notes' => $invoice->notes,
        ];

        // Add billing reference for credit/debit notes
        if (in_array($documentType, ['credit_note', 'debit_note'], true)) {
            $payload['billing_reference_id'] = $invoice->originalInvoice?->invoice_number;
            $payload['adjustment_reason'] = $invoice->notes;
        }

        return $payload;
    }

    /**
     * Map ERP invoice_type to ZATCA type + document_type.
     *
     * @return array{0: string, 1: string}
     */
    private static function mapInvoiceType(string $invoiceType): array
    {
        return match ($invoiceType) {
            Invoice::TYPE_STANDARD => ['standard', 'invoice'],
            Invoice::TYPE_SIMPLIFIED => ['simplified', 'invoice'],
            Invoice::TYPE_CREDIT_NOTE => ['standard', 'credit_note'],
            Invoice::TYPE_DEBIT_NOTE => ['standard', 'debit_note'],
            default => ['standard', 'invoice'],
        };
    }

    /**
     * Build the buyer address from the invoice's Contact relationship.
     *
     * Falls back to the invoice's billing_address field if the contact
     * relationship is unavailable.
     *
     * @return array<string, string|null>
     */
    private static function buildBuyerAddress(Invoice $invoice): array
    {
        $contact = $invoice->customer;

        if ($contact !== null) {
            return [
                'street' => $contact->billing_address_line_1,
                'city' => $contact->billing_city,
                'district' => $contact->billing_state,
                'building_number' => $contact->billing_address_line_2,
                'postal_code' => $contact->billing_postal_code,
                'country_code' => $contact->billing_country_code,
            ];
        }

        // Fallback: billing_address may be a string or array on the invoice
        $billingAddress = $invoice->billing_address;

        if (is_array($billingAddress)) {
            return [
                'street' => $billingAddress['street'] ?? $billingAddress['address_line_1'] ?? null,
                'city' => $billingAddress['city'] ?? null,
                'district' => $billingAddress['district'] ?? $billingAddress['state'] ?? null,
                'building_number' => $billingAddress['building_number'] ?? $billingAddress['address_line_2'] ?? null,
                'postal_code' => $billingAddress['postal_code'] ?? null,
                'country_code' => $billingAddress['country_code'] ?? null,
            ];
        }

        return [
            'street' => is_string($billingAddress) ? $billingAddress : null,
            'city' => null,
            'district' => null,
            'building_number' => null,
            'postal_code' => null,
            'country_code' => null,
        ];
    }

    /**
     * Transform invoice lines into the ZATCA line-item format.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function transformLines(Invoice $invoice): array
    {
        return $invoice->lines->map(fn ($line) => [
            'description' => $line->description,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'tax_rate' => (function () use ($line): float {
                if ($line->tax_rate === null || $line->tax_rate === '') {
                    throw new \InvalidArgumentException('Tax rate is required for ZATCA invoice lines — a null tax rate is not valid.');
                }
                return (float) $line->tax_rate;
            })(),
            'tax_category' => self::mapTaxCategory($line->tax_code),
            'tax_exemption_code' => $line->tax_exemption_code,
            'tax_exemption_reason' => $line->tax_exemption_reason,
            'discount_amount' => (float) $line->discount_amount,
            'tax_amount' => (float) $line->tax_amount,
            'subtotal' => (float) $line->subtotal,
            'total' => (float) $line->total,
        ])->toArray();
    }

    /**
     * Map ERP tax_code to ZATCA tax category code.
     *
     * ZATCA uses: S (Standard), Z (Zero-rated), E (Exempt), O (Out of scope).
     */
    private static function mapTaxCategory(?string $taxCode): string
    {
        return match ($taxCode) {
            'S', 'standard' => 'S',
            'Z', 'zero' => 'Z',
            'E', 'exempt' => 'E',
            'O', 'out_of_scope' => 'O',
            default => 'S',
        };
    }
}
