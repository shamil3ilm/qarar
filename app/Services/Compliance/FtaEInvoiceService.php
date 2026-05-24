<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\FtaEInvoiceSubmission;
use InvalidArgumentException;

/**
 * UAE FTA E-Invoice Service (EmaraTax / FTA e-invoicing mandate).
 *
 * Responsibilities:
 *  - Generate UBL 2.1 XML for each invoice / credit note
 *  - Embed TLV QR code per FTA specification
 *  - Persist submission records with status tracking
 *  - Transition statuses: pending → submitted → accepted/rejected
 */
class FtaEInvoiceService
{
    public function __construct(
        private readonly FtaUblBuilder $ublBuilder,
    ) {}

    /**
     * Prepare a new FTA e-invoice submission (status = pending).
     *
     * @param  array<string, mixed>  $data  Must include: organization_id, invoice_number, issue_date, subtotal, tax_amount, total_amount
     * @param  int  $userId
     */
    public function prepare(array $data, int $userId): FtaEInvoiceSubmission
    {
        $this->validate($data);

        $ubl     = $this->ublBuilder->buildInvoice($data);
        $qrCode  = $this->ublBuilder->buildQrCode($data);

        return FtaEInvoiceSubmission::create([
            'organization_id'  => (int) $data['organization_id'],
            'invoice_id'       => $data['invoice_id'] ?? null,
            'invoice_number'   => $data['invoice_number'],
            'invoice_type'     => $data['invoice_type'] ?? FtaEInvoiceSubmission::TYPE_INVOICE,
            'issue_date'       => $data['issue_date'],
            'currency_code'    => $data['currency_code'] ?? 'AED',
            'seller_trn'       => $data['seller_trn'] ?? null,
            'buyer_trn'        => $data['buyer_trn'] ?? null,
            'subtotal'         => (float) $data['subtotal'],
            'tax_amount'       => (float) $data['tax_amount'],
            'total_amount'     => (float) $data['total_amount'],
            'tax_rate'         => (float) ($data['tax_rate'] ?? FtaEInvoiceSubmission::UAE_VAT_RATE),
            'ubl_xml'          => $ubl,
            'qr_code_data'     => $qrCode,
            'status'           => FtaEInvoiceSubmission::STATUS_PENDING,
            'billing_reference' => $data['billing_reference'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'created_by'       => $userId,
        ]);
    }

    /**
     * Mark a pending submission as submitted (e.g. after sending to FTA API).
     */
    public function markSubmitted(FtaEInvoiceSubmission $submission, ?string $ftaSubmissionId = null): FtaEInvoiceSubmission
    {
        if (!$submission->isPending()) {
            throw new InvalidArgumentException(
                "Only pending submissions can be marked submitted. Current status: {$submission->status}."
            );
        }

        $submission->update([
            'status'            => FtaEInvoiceSubmission::STATUS_SUBMITTED,
            'fta_submission_id' => $ftaSubmissionId,
            'submitted_at'      => now(),
        ]);

        return $submission->fresh();
    }

    /**
     * Record FTA acceptance of a submitted invoice.
     */
    public function markAccepted(FtaEInvoiceSubmission $submission, string $ftaResponse = ''): FtaEInvoiceSubmission
    {
        $submission->update([
            'status'           => FtaEInvoiceSubmission::STATUS_ACCEPTED,
            'fta_response'     => $ftaResponse,
            'acknowledged_at'  => now(),
        ]);

        return $submission->fresh();
    }

    /**
     * Record FTA rejection of a submitted invoice.
     */
    public function markRejected(FtaEInvoiceSubmission $submission, string $ftaResponse = ''): FtaEInvoiceSubmission
    {
        $submission->update([
            'status'       => FtaEInvoiceSubmission::STATUS_REJECTED,
            'fta_response' => $ftaResponse,
        ]);

        return $submission->fresh();
    }

    // -------------------------------------------------------------------------

    private function validate(array $data): void
    {
        $required = ['organization_id', 'invoice_number', 'issue_date', 'subtotal', 'tax_amount', 'total_amount'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("FTA e-invoice preparation requires '{$field}'.");
            }
        }

        $taxAmount = (float) $data['tax_amount'];
        $subtotal  = (float) $data['subtotal'];
        $total     = (float) $data['total_amount'];

        if ($subtotal < 0 || $taxAmount < 0 || $total < 0) {
            throw new InvalidArgumentException('FTA e-invoice amounts must be non-negative.');
        }
    }
}
