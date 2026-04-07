<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\DocumentDownloadToken;
use App\Models\Purchase\Bill;
use App\Models\Sales\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class DocumentService
{
    public const ACCESS_GRACE_HOURS = 24;
    public const DEFAULT_LINK_TTL_HOURS = 72;

    /**
     * Generate a time-limited, single-use download token for a document.
     *
     * @param  string   $documentType  One of the DocumentDownloadToken::TYPE_* constants.
     * @param  int      $documentId    Primary key of the target document record.
     * @param  int      $organizationId  Owning organization.
     * @param  int|null $generatedBy   User that requested the link (nullable for system-generated links).
     * @param  int      $ttlHours      How many hours until the link expires absolutely.
     */
    public function generateDownloadToken(
        string $documentType,
        int $documentId,
        int $organizationId,
        ?int $generatedBy = null,
        int $ttlHours = self::DEFAULT_LINK_TTL_HOURS
    ): DocumentDownloadToken {
        $token = bin2hex(random_bytes(20));

        return DocumentDownloadToken::create([
            'document_id'     => $documentId,
            'document_type'   => $documentType,
            'expires_at'      => now()->addHours($ttlHours),
            'generated_by'    => $generatedBy,
            'organization_id' => $organizationId,
            'token'           => $token,
        ]);
    }

    /**
     * Render an Invoice as a PDF binary string.
     */
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'lines.product', 'lines.variant']);

        return Pdf::loadView('pdf.invoice', compact('invoice'))->output();
    }

    /**
     * Render a Bill as a PDF binary string.
     */
    public function generateBillPdf(Bill $bill): string
    {
        $bill->loadMissing(['supplier', 'lines.product']);

        return Pdf::loadView('pdf.bill', compact('bill'))->output();
    }

    /**
     * Resolve a document record by type and ID, scoped to the given organization.
     *
     * @throws ModelNotFoundException When the document cannot be found.
     * @throws \InvalidArgumentException When the document type is unknown.
     */
    public function resolveDocument(string $documentType, int $documentId, int $organizationId): Invoice|Bill
    {
        return match ($documentType) {
            DocumentDownloadToken::TYPE_INVOICE,
            DocumentDownloadToken::TYPE_RECEIPT => $this->findInvoice($documentId, $organizationId),

            DocumentDownloadToken::TYPE_BILL => $this->findBill($documentId, $organizationId),

            default => throw new \InvalidArgumentException(
                "Unknown document type: {$documentType}"
            ),
        };
    }

    private function findInvoice(int $documentId, int $organizationId): Invoice
    {
        $invoice = Invoice::withoutGlobalScope('organization')
            ->where('id', $documentId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($invoice === null) {
            Log::warning('DocumentService: invoice not found', [
                'document_id'     => $documentId,
                'organization_id' => $organizationId,
            ]);

            throw new ModelNotFoundException("Invoice #{$documentId} not found.");
        }

        return $invoice;
    }

    private function findBill(int $documentId, int $organizationId): Bill
    {
        $bill = Bill::withoutGlobalScope('organization')
            ->where('id', $documentId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($bill === null) {
            Log::warning('DocumentService: bill not found', [
                'document_id'     => $documentId,
                'organization_id' => $organizationId,
            ]);

            throw new ModelNotFoundException("Bill #{$documentId} not found.");
        }

        return $bill;
    }
}
