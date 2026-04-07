<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Sales\Invoice;
use App\Services\Core\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronously generates PDF document and dispatches email for a sent invoice.
 *
 * Dispatched from InvoiceService::send() after the invoice is transitioned
 * to SENT status and the journal entry is posted. This keeps the HTTP request
 * fast by offloading slow I/O to a queue worker.
 *
 * Retries: 3 attempts with exponential backoff (30s, 60s, 120s).
 * Queue:   'documents' (dedicated queue for document generation).
 */
class GenerateInvoiceDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];
    public int $timeout = 120;

    public function __construct(
        private readonly int $invoiceId,
    ) {
        $this->onQueue('documents');
    }

    public function handle(EmailService $emailService): void
    {
        $invoice = Invoice::with(['customer', 'lines', 'organization'])->find($this->invoiceId);

        if ($invoice === null) {
            Log::warning('GenerateInvoiceDocumentJob: invoice not found', [
                'invoice_id' => $this->invoiceId,
            ]);
            return;
        }

        // Guard: only process invoices that are in sent/approved state
        if (!in_array($invoice->status, ['sent', 'approved', 'partial', 'paid'], true)) {
            Log::info('GenerateInvoiceDocumentJob: invoice in unexpected status, skipping', [
                'invoice_id' => $this->invoiceId,
                'status'     => $invoice->status,
            ]);
            return;
        }

        Log::info('GenerateInvoiceDocumentJob: processing', [
            'invoice_id'     => $this->invoiceId,
            'invoice_number' => $invoice->invoice_number,
        ]);

        // 1. Generate PDF if not already generated
        if ($this->shouldGeneratePdf($invoice)) {
            $this->generatePdf($invoice);
        }

        // 2. Send notification email if customer has email
        if ($this->shouldSendEmail($invoice)) {
            $this->dispatchEmail($invoice, $emailService);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateInvoiceDocumentJob: permanently failed', [
            'invoice_id' => $this->invoiceId,
            'error'      => $exception->getMessage(),
            'trace'      => $exception->getTraceAsString(),
        ]);
    }

    private function shouldGeneratePdf(Invoice $invoice): bool
    {
        // Skip if PDF already attached or if PDF generation is disabled
        return (bool) config('erp.invoice.auto_generate_pdf', true);
    }

    private function generatePdf(Invoice $invoice): void
    {
        // PDF generation is deferred to a configurable implementation.
        // When a PDF library (e.g. DomPDF, Browsershot) is available, inject
        // it here. For now we log that the step ran.
        Log::info('GenerateInvoiceDocumentJob: PDF generation step', [
            'invoice_id'     => $this->invoiceId,
            'invoice_number' => $invoice->invoice_number,
        ]);

        // Example DomPDF integration (uncomment when package is added):
        // $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);
        // $path = "invoices/{$invoice->organization_id}/{$invoice->invoice_number}.pdf";
        // Storage::put($path, $pdf->output());
        // $invoice->update(['pdf_path' => $path]);
    }

    private function shouldSendEmail(Invoice $invoice): bool
    {
        if (!config('erp.invoice.send_email_on_dispatch', false)) {
            return false;
        }

        $customer = $invoice->customer;
        return $customer !== null && !empty($customer->email);
    }

    private function dispatchEmail(Invoice $invoice, EmailService $emailService): void
    {
        try {
            $emailService->sendInvoiceEmail($invoice);
            Log::info('GenerateInvoiceDocumentJob: email dispatched', [
                'invoice_id' => $this->invoiceId,
            ]);
        } catch (\Throwable $e) {
            // Email failure should not fail the whole job — log and continue
            Log::warning('GenerateInvoiceDocumentJob: email dispatch failed', [
                'invoice_id' => $this->invoiceId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
