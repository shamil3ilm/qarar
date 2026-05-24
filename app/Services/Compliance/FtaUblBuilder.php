<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\FtaEInvoiceSubmission;

/**
 * UAE FTA UBL 2.1 XML Builder.
 *
 * Generates UBL 2.1 compliant XML for UAE FTA e-invoicing (EmaraTax).
 * Currency is AED, VAT rate is 5%, TRN format is 15 digits.
 *
 * UBL 2.1 namespace: urn:oasis:names:specification:ubl:schema:xsd:Invoice-2
 * UAE FTA extension: FTA-specific QR code (TLV encoded) appended as UBLExtension.
 */
class FtaUblBuilder
{
    /** UAE VAT rate */
    private const VAT_RATE = 5.0;

    /**
     * Build a UBL 2.1 Invoice XML string from submission data.
     *
     * @param  array<string, mixed>  $data  Invoice payload
     * @return string  UBL 2.1 XML
     */
    public function buildInvoice(array $data): string
    {
        $invoiceType = $data['invoice_type'] ?? FtaEInvoiceSubmission::TYPE_INVOICE;
        $typeCode    = $this->mapTypeCode($invoiceType);

        $sellerTrn = $data['seller_trn'] ?? '';
        $buyerTrn  = $data['buyer_trn']  ?? '';
        $currency  = $data['currency_code'] ?? 'AED';
        $issueDate = $data['issue_date'] ?? date('Y-m-d');
        $invoiceNo = $data['invoice_number'] ?? '';

        $subtotal  = number_format((float) ($data['subtotal'] ?? 0), 2, '.', '');
        $taxAmount = number_format((float) ($data['tax_amount'] ?? 0), 2, '.', '');
        $total     = number_format((float) ($data['total_amount'] ?? 0), 2, '.', '');
        $taxRate   = number_format((float) ($data['tax_rate'] ?? self::VAT_RATE), 2, '.', '');

        $sellerName = htmlspecialchars($data['seller_name'] ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $buyerName  = htmlspecialchars($data['buyer_name']  ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $lines = $this->buildLines($data['lines'] ?? [], $currency);

        $billingRef = '';
        if (!empty($data['billing_reference'])) {
            $ref = htmlspecialchars($data['billing_reference'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $billingRef = <<<XML
    <cac:BillingReference>
        <cac:InvoiceDocumentReference>
            <cbc:ID>{$ref}</cbc:ID>
        </cac:InvoiceDocumentReference>
    </cac:BillingReference>
XML;
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>urn:fta.gov.ae:einvoice:1.0</cbc:CustomizationID>
    <cbc:ProfileID>reporting:1.0</cbc:ProfileID>
    <cbc:ID>{$invoiceNo}</cbc:ID>
    <cbc:IssueDate>{$issueDate}</cbc:IssueDate>
    <cbc:InvoiceTypeCode listID="UN/ECE 1001 Subset" listAgencyID="6">{$typeCode}</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>{$currency}</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>{$currency}</cbc:TaxCurrencyCode>
{$billingRef}
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyName>
                <cbc:Name>{$sellerName}</cbc:Name>
            </cac:PartyName>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>{$sellerTrn}</cbc:CompanyID>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyName>
                <cbc:Name>{$buyerName}</cbc:Name>
            </cac:PartyName>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>{$buyerTrn}</cbc:CompanyID>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="{$currency}">{$taxAmount}</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="{$currency}">{$subtotal}</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="{$currency}">{$taxAmount}</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>{$taxRate}</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="{$currency}">{$subtotal}</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="{$currency}">{$subtotal}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="{$currency}">{$total}</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="{$currency}">{$total}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
{$lines}
</Invoice>
XML;

        return $xml;
    }

    /**
     * Build the TLV QR code data string (FTA specification).
     *
     * Tags:
     *   1 = Seller name, 2 = Seller TRN, 3 = Invoice date,
     *   4 = Invoice total (with VAT), 5 = VAT total
     */
    public function buildQrCode(array $data): string
    {
        $fields = [
            1 => $data['seller_name']    ?? '',
            2 => $data['seller_trn']     ?? '',
            3 => $data['issue_date']     ?? date('Y-m-d'),
            4 => number_format((float) ($data['total_amount'] ?? 0), 2, '.', ''),
            5 => number_format((float) ($data['tax_amount']   ?? 0), 2, '.', ''),
        ];

        $tlv = '';
        foreach ($fields as $tag => $value) {
            $encoded = mb_convert_encoding((string) $value, 'UTF-8');
            $length  = strlen($encoded);
            $tlv    .= chr($tag) . chr($length) . $encoded;
        }

        return base64_encode($tlv);
    }

    // -------------------------------------------------------------------------

    private function mapTypeCode(string $type): string
    {
        return match ($type) {
            FtaEInvoiceSubmission::TYPE_CREDIT_NOTE => '381',
            FtaEInvoiceSubmission::TYPE_DEBIT_NOTE  => '383',
            default                                 => '388', // Standard invoice
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function buildLines(array $lines, string $currency): string
    {
        if (empty($lines)) {
            return '';
        }

        $xml = '';
        foreach ($lines as $i => $line) {
            $seq      = $i + 1;
            $qty      = number_format((float) ($line['quantity'] ?? 1), 2, '.', '');
            $price    = number_format((float) ($line['unit_price'] ?? 0), 2, '.', '');
            $lineAmt  = number_format((float) ($line['line_total'] ?? 0), 2, '.', '');
            $desc     = htmlspecialchars($line['description'] ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $xml .= <<<XML

    <cac:InvoiceLine>
        <cbc:ID>{$seq}</cbc:ID>
        <cbc:InvoicedQuantity unitCode="PCE">{$qty}</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="{$currency}">{$lineAmt}</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Description>{$desc}</cbc:Description>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="{$currency}">{$price}</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>
XML;
        }

        return $xml;
    }
}
