<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Tax\IndiaEInvoiceSubmission;
use InvalidArgumentException;

/**
 * India IRP E-Invoice Service (GSTN / NIC portal).
 *
 * Manages the full lifecycle of an India GST e-invoice:
 *   pending → submitted → accepted / rejected
 *
 * The IRP (Invoice Registration Portal) validates the e-invoice JSON,
 * computes the IRN (64-char SHA-256 hash), digitally signs the payload,
 * and returns an acknowledgement number + QR code.
 *
 * This service generates the payload locally and records the IRP response
 * once received. In production, a dedicated GSTN API adapter (not included
 * here) sends the JSON to the IRP sandbox/production endpoint.
 */
class IndiaIrpEInvoiceService
{
    public function __construct(
        private readonly IndiaIrnBuilder $irnBuilder,
    ) {}

    /**
     * Prepare an e-invoice submission record (status = pending).
     *
     * Computes the IRN and full NIC JSON payload, persists a pending record.
     *
     * @param  array<string, mixed>  $data  Must include: organization_id, document_number,
     *                                      document_date (DD/MM/YYYY), gstin_seller,
     *                                      taxable_value, total_amount.
     */
    public function prepare(array $data, int $userId): IndiaEInvoiceSubmission
    {
        $this->validate($data);

        $payload = $this->irnBuilder->buildPayload($data);
        $irn     = $payload['_irn'];
        $qr      = $this->irnBuilder->buildQrData($data, $irn);

        return IndiaEInvoiceSubmission::create([
            'organization_id'  => (int) $data['organization_id'],
            'invoice_id'       => $data['invoice_id'] ?? null,
            'document_number'  => $data['document_number'],
            'document_type'    => $data['document_type'] ?? IndiaEInvoiceSubmission::DOC_TYPE_INVOICE,
            'document_date'    => $this->normalizeDate($data['document_date']),
            'gstin_seller'     => $data['gstin_seller'],
            'gstin_buyer'      => $data['gstin_buyer'] ?? null,
            'seller_name'      => $data['seller_name'] ?? null,
            'buyer_name'       => $data['buyer_name'] ?? null,
            'seller_state_code' => $data['seller_state_code'] ?? null,
            'buyer_state_code'  => $data['buyer_state_code'] ?? null,
            'taxable_value'    => (float) $data['taxable_value'],
            'cgst_amount'      => (float) ($data['cgst_amount'] ?? 0),
            'sgst_amount'      => (float) ($data['sgst_amount'] ?? 0),
            'igst_amount'      => (float) ($data['igst_amount'] ?? 0),
            'cess_amount'      => (float) ($data['cess_amount'] ?? 0),
            'total_amount'     => (float) $data['total_amount'],
            'irn'              => $irn,
            'einvoice_json'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'signed_qr_code'   => $qr,
            'status'           => IndiaEInvoiceSubmission::STATUS_PENDING,
            'notes'            => $data['notes'] ?? null,
            'created_by'       => $userId,
        ]);
    }

    /**
     * Record that the e-invoice has been sent to the IRP (status = submitted).
     */
    public function markSubmitted(IndiaEInvoiceSubmission $submission): IndiaEInvoiceSubmission
    {
        if (!$submission->isPending()) {
            throw new InvalidArgumentException(
                "Only pending submissions can be marked submitted. Current: {$submission->status}."
            );
        }

        $submission->update(['status' => IndiaEInvoiceSubmission::STATUS_SUBMITTED]);
        return $submission->fresh();
    }

    /**
     * Record IRP acceptance: store acknowledgement number + signed response.
     */
    public function markAccepted(
        IndiaEInvoiceSubmission $submission,
        string $ackNumber,
        string $signedInvoice = '',
        ?string $signedQr = null,
    ): IndiaEInvoiceSubmission {
        $submission->update([
            'status'         => IndiaEInvoiceSubmission::STATUS_ACCEPTED,
            'irp_ack_number' => $ackNumber,
            'irp_ack_date'   => now(),
            'signed_invoice' => $signedInvoice,
            'signed_qr_code' => $signedQr ?? $submission->signed_qr_code,
        ]);

        return $submission->fresh();
    }

    /**
     * Record IRP rejection.
     */
    public function markRejected(IndiaEInvoiceSubmission $submission, string $reason): IndiaEInvoiceSubmission
    {
        $submission->update([
            'status'       => IndiaEInvoiceSubmission::STATUS_REJECTED,
            'irp_response' => $reason,
        ]);

        return $submission->fresh();
    }

    /**
     * Cancel an accepted e-invoice (within 24 hours of IRP acceptance, per GSTN rules).
     *
     * @throws InvalidArgumentException if the submission is not in accepted status.
     */
    public function cancel(IndiaEInvoiceSubmission $submission, string $reason): IndiaEInvoiceSubmission
    {
        if (!$submission->isAccepted()) {
            throw new InvalidArgumentException(
                'Only accepted e-invoices can be cancelled.'
            );
        }

        $submission->update([
            'status'        => IndiaEInvoiceSubmission::STATUS_CANCELLED,
            'cancel_reason' => $reason,
            'cancelled_at'  => now(),
        ]);

        return $submission->fresh();
    }

    // -------------------------------------------------------------------------

    private function validate(array $data): void
    {
        foreach (['organization_id', 'document_number', 'document_date', 'gstin_seller', 'taxable_value', 'total_amount'] as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("India e-invoice preparation requires '{$field}'.");
            }
        }

        $gstin = $data['gstin_seller'];
        if (strlen($gstin) !== 15) {
            throw new InvalidArgumentException("gstin_seller must be exactly 15 characters. Got: '{$gstin}'.");
        }
    }

    /**
     * Normalize document_date to Y-m-d for DB storage (accepts DD/MM/YYYY or Y-m-d).
     */
    private function normalizeDate(string $date): string
    {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            [$d, $m, $y] = explode('/', $date);
            return "{$y}-{$m}-{$d}";
        }

        return $date;
    }
}
