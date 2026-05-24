<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\QatarGtaSubmission;
use InvalidArgumentException;

/**
 * Qatar GTA E-Invoicing Service.
 *
 * Qatar General Tax Authority (GTA) mandated e-invoicing in phases,
 * modeled on the ZATCA framework. This service:
 *  - Generates UBL 2.1 XML adapted for Qatar TRN identifiers
 *  - Builds a TLV QR code in the GTA format
 *  - Tracks submission status: pending → submitted → accepted/rejected
 *
 * Currency: QAR
 * TRN format: 11-digit Tax Registration Number
 */
class QatarGtaEInvoiceService
{
    /** Qatar selective tax rate (varies by product; 100% tobacco, 50% carbonated) */
    public const SELECTIVE_TAX_RATE = 0.0; // Standard commercial invoices use 0% unless selective

    // -------------------------------------------------------------------------
    // XML builder
    // -------------------------------------------------------------------------

    /**
     * Generate UBL 2.1 XML for Qatar GTA submission.
     *
     * @param  array<string, mixed>  $data
     */
    public function buildXml(array $data): string
    {
        $invoiceNo   = htmlspecialchars($data['invoice_number'] ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $issueDate   = $data['issue_date'] ?? date('Y-m-d');
        $currency    = $data['currency_code'] ?? 'QAR';
        $sellerTrn   = $data['seller_trn'] ?? '';
        $buyerTrn    = $data['buyer_trn'] ?? '';
        $sellerName  = htmlspecialchars($data['seller_name'] ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $buyerName   = htmlspecialchars($data['buyer_name'] ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $subtotal    = number_format((float) ($data['subtotal'] ?? 0), 2, '.', '');
        $taxAmount   = number_format((float) ($data['tax_amount'] ?? 0), 2, '.', '');
        $total       = number_format((float) ($data['total_amount'] ?? 0), 2, '.', '');

        $invoiceType = $data['invoice_type'] ?? QatarGtaSubmission::TYPE_INVOICE;
        $typeCode    = match ($invoiceType) {
            QatarGtaSubmission::TYPE_CREDIT_NOTE => '381',
            QatarGtaSubmission::TYPE_DEBIT_NOTE  => '383',
            default                              => '388',
        };

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

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>urn:gta.gov.qa:einvoice:1.0</cbc:CustomizationID>
    <cbc:ProfileID>reporting:1.0</cbc:ProfileID>
    <cbc:ID>{$invoiceNo}</cbc:ID>
    <cbc:IssueDate>{$issueDate}</cbc:IssueDate>
    <cbc:InvoiceTypeCode listID="UN/ECE 1001 Subset">{$typeCode}</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>{$currency}</cbc:DocumentCurrencyCode>
{$billingRef}
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>{$sellerName}</cbc:Name></cac:PartyName>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>{$sellerTrn}</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>TRN</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>{$buyerName}</cbc:Name></cac:PartyName>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>{$buyerTrn}</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>TRN</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="{$currency}">{$taxAmount}</cbc:TaxAmount>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="{$currency}">{$subtotal}</cbc:LineExtensionAmount>
        <cbc:TaxInclusiveAmount currencyID="{$currency}">{$total}</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="{$currency}">{$total}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
</Invoice>
XML;
    }

    /**
     * Build TLV QR code data for Qatar GTA.
     * Tags mirror the UAE FTA spec (common GCC reference).
     */
    public function buildQrCode(array $data): string
    {
        $fields = [
            1 => $data['seller_name']    ?? '',
            2 => $data['seller_trn']     ?? '',
            3 => $data['issue_date']     ?? date('Y-m-d'),
            4 => number_format((float) ($data['total_amount'] ?? 0), 2, '.', ''),
            5 => number_format((float) ($data['tax_amount'] ?? 0), 2, '.', ''),
        ];

        $tlv = '';
        foreach ($fields as $tag => $value) {
            $encoded = (string) $value;
            $length  = strlen($encoded);
            $tlv    .= chr($tag) . chr($length) . $encoded;
        }

        return base64_encode($tlv);
    }

    // -------------------------------------------------------------------------
    // Submission lifecycle
    // -------------------------------------------------------------------------

    /**
     * Prepare a new GTA submission record (status = pending).
     *
     * @param  array<string, mixed>  $data
     */
    public function prepare(array $data, int $userId): QatarGtaSubmission
    {
        $this->validate($data);

        $xml    = $this->buildXml($data);
        $qrCode = $this->buildQrCode($data);

        return QatarGtaSubmission::create([
            'organization_id'  => (int) $data['organization_id'],
            'invoice_id'       => $data['invoice_id'] ?? null,
            'invoice_number'   => $data['invoice_number'],
            'invoice_type'     => $data['invoice_type'] ?? QatarGtaSubmission::TYPE_INVOICE,
            'issue_date'       => $data['issue_date'],
            'currency_code'    => $data['currency_code'] ?? 'QAR',
            'seller_trn'       => $data['seller_trn'] ?? null,
            'buyer_trn'        => $data['buyer_trn'] ?? null,
            'subtotal'         => (float) $data['subtotal'],
            'tax_amount'       => (float) $data['tax_amount'],
            'total_amount'     => (float) $data['total_amount'],
            'invoice_xml'      => $xml,
            'qr_code_data'     => $qrCode,
            'status'           => QatarGtaSubmission::STATUS_PENDING,
            'billing_reference' => $data['billing_reference'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'created_by'       => $userId,
        ]);
    }

    public function markSubmitted(QatarGtaSubmission $submission, ?string $gtaSubmissionId = null): QatarGtaSubmission
    {
        if (!$submission->isPending()) {
            throw new InvalidArgumentException(
                "Only pending submissions can be marked submitted. Current: {$submission->status}."
            );
        }

        $submission->update([
            'status'            => QatarGtaSubmission::STATUS_SUBMITTED,
            'gta_submission_id' => $gtaSubmissionId,
            'submitted_at'      => now(),
        ]);

        return $submission->fresh();
    }

    public function markAccepted(QatarGtaSubmission $submission, string $response = ''): QatarGtaSubmission
    {
        $submission->update([
            'status'           => QatarGtaSubmission::STATUS_ACCEPTED,
            'gta_response'     => $response,
            'acknowledged_at'  => now(),
        ]);

        return $submission->fresh();
    }

    public function markRejected(QatarGtaSubmission $submission, string $response = ''): QatarGtaSubmission
    {
        $submission->update([
            'status'       => QatarGtaSubmission::STATUS_REJECTED,
            'gta_response' => $response,
        ]);

        return $submission->fresh();
    }

    // -------------------------------------------------------------------------

    private function validate(array $data): void
    {
        foreach (['organization_id', 'invoice_number', 'issue_date', 'subtotal', 'tax_amount', 'total_amount'] as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Qatar GTA submission requires '{$field}'.");
            }
        }
    }
}
