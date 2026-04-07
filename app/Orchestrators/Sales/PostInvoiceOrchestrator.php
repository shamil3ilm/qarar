<?php

declare(strict_types=1);

namespace App\Orchestrators\Sales;

use App\Events\Sales\InvoicePosted;
use App\Jobs\GenerateInvoiceDocumentJob;
use App\Jobs\RetryComplianceSubmission;
use App\Models\Core\UserEvent;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Services\Accounting\CreditManagementService;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Compliance\CompliPayClient;
use App\Services\Core\UserEventService;
use App\Services\Inventory\StockService;
use App\Services\Sales\RebateAccrualService;
use App\Traits\StructuredLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates all side-effects triggered when a sales invoice is posted.
 *
 * Responsibilities:
 * - Runs the atomic core inside a DB transaction: credit-limit gate, journal
 *   entry creation, inventory deduction, and status flip to SENT.
 * - After the transaction commits, dispatches the InvoicePosted domain event so
 *   downstream listeners (COPA auto-post, customer-balance update) are triggered.
 * - Handles non-transactional side-effects: ZATCA submission, customer
 *   notification, rebate accrual, PDF generation, user-event tracking.
 *
 * This class is the canonical reference implementation of Architecture Rule 2:
 * "Cross-module flows MUST use an orchestrator; no service may call another
 * module's service directly to satisfy a multi-step business flow."
 *
 * CONTRACT:
 * - execute() must only be called once per invoice (guarded upstream by
 *   FinancialIdempotencyService in InvoiceService::send()).
 * - The invoice passed in must be in STATUS_DRAFT; that guard lives in InvoiceService.
 */
class PostInvoiceOrchestrator
{
    use StructuredLogger;

    public function __construct(
        private readonly JournalEntryFactory $journalEntryFactory,
        private readonly StockService $stockService,
        private readonly CreditManagementService $creditManagementService,
        private readonly CompliPayClient $compliPayClient,
        private readonly UserEventService $userEventService,
        private readonly RebateAccrualService $rebateAccrualService,
    ) {}

    /**
     * Execute the full post-invoice flow for a draft invoice.
     *
     * @param  Invoice  $invoice  A draft invoice (STATUS_DRAFT).
     * @return Invoice  The same invoice reloaded after status is flipped to SENT.
     */
    public function execute(Invoice $invoice): Invoice
    {
        // ─── PHASE 1: atomic core ─────────────────────────────────────────────
        $invoice = DB::transaction(function () use ($invoice): Invoice {
            // Pessimistic lock to serialise concurrent send attempts.
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            // Credit-limit / credit-hold gate at confirmation time.
            // Checked again here (vs. create()) because exposure may have changed.
            if ($invoice->invoice_type !== Invoice::TYPE_CREDIT_NOTE) {
                $customer = $invoice->customer ?? Contact::find($invoice->customer_id);
                if ($customer) {
                    $this->assertCreditLimit($customer, (float) $invoice->total);
                }
            }

            // Post double-entry journal (AR debit / revenue+tax credit).
            $journal = $this->journalEntryFactory->forInvoice($invoice);

            // Deduct stock levels for inventory-tracked lines.
            $this->deductInventory($invoice);

            $invoice->update([
                'status'           => Invoice::STATUS_SENT,
                'journal_entry_id' => $journal->id,
            ]);

            return $invoice->fresh()->load('customer');
        });

        // ─── PHASE 2: domain event ────────────────────────────────────────────
        // Dispatched AFTER the transaction commits so listeners never observe a
        // partially-written invoice. This is THE fix for the latent bug where
        // PostCopaOnInvoicePostedListener and UpdateCustomerBalanceOnInvoicePostedListener
        // were registered but the event was never fired.
        InvoicePosted::dispatch($invoice);

        // ─── PHASE 3: non-transactional side-effects ──────────────────────────
        // External API calls and async jobs must not hold an open DB transaction.

        $this->handleZatcaSubmission($invoice);
        $this->notifyCustomer($invoice);
        $this->accrueRebates($invoice);
        $this->trackUserEvent($invoice);

        // Offload PDF generation + email dispatch to the queue.
        // Dispatched after the transaction so the worker always finds STATUS_SENT.
        GenerateInvoiceDocumentJob::dispatch($invoice->id);

        return $invoice;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Assert the customer has sufficient credit for this invoice amount.
     *
     * @throws \InvalidArgumentException when on active credit hold.
     * @throws \App\Exceptions\ApiException when the limit would be breached.
     */
    private function assertCreditLimit(Contact $customer, float $amount): void
    {
        $activeHold = \App\Models\Accounting\CreditHold::where('organization_id', $customer->organization_id)
            ->where('contact_id', $customer->id)
            ->active()
            ->first();

        if ($activeHold) {
            throw new \InvalidArgumentException(
                "Customer is on credit hold: {$activeHold->hold_reason}"
            );
        }

        $creditLimit = \App\Models\Accounting\CreditLimit::where('organization_id', $customer->organization_id)
            ->where('contact_id', $customer->id)
            ->active()
            ->lockForUpdate()
            ->first();

        if (!$creditLimit) {
            return;
        }

        if ($creditLimit->isBlocked()) {
            \App\Models\Accounting\CreditHold::firstOrCreate(
                ['organization_id' => $customer->organization_id, 'contact_id' => $customer->id, 'released_at' => null],
                ['held_at' => now(), 'hold_reason' => 'Credit risk class is BLOCKED', 'held_by' => auth()->id()]
            );

            throw new \InvalidArgumentException('Customer is on credit hold: Credit risk class is BLOCKED');
        }

        $exposure    = $this->creditManagementService->getCreditExposure($customer);
        $newTotal    = bcadd((string) $exposure['total_exposure'], (string) $amount, 4);
        $limit       = (string) $creditLimit->credit_limit;

        if (bccomp($newTotal, $limit, 4) > 0) {
            \App\Models\Accounting\CreditHold::firstOrCreate(
                ['organization_id' => $customer->organization_id, 'contact_id' => $customer->id, 'released_at' => null],
                [
                    'held_at'     => now(),
                    'hold_reason' => sprintf(
                        'Invoice exceeds credit limit. Balance: %s, Limit: %s, New: %s',
                        number_format((float) $exposure['total_exposure'], 2),
                        number_format((float) $limit, 2),
                        number_format($amount, 2)
                    ),
                    'held_by' => auth()->id(),
                ]
            );

            throw \App\Exceptions\ApiException::fromError(
                \App\Exceptions\ErrorCodes::SALES_INSUFFICIENT_CREDIT,
                ['balance' => (float) $exposure['total_exposure'], 'limit' => (float) $limit, 'new_amount' => $amount],
                sprintf(
                    'Invoice exceeds customer credit limit. Balance: %s, Limit: %s, New: %s',
                    number_format((float) $exposure['total_exposure'], 2),
                    number_format((float) $limit, 2),
                    number_format($amount, 2)
                )
            );
        }
    }

    private function deductInventory(Invoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            if ($line->product_id && $line->product?->track_inventory && $line->warehouse_id) {
                $this->stockService->recordSale(
                    productId: $line->product_id,
                    warehouseId: $line->warehouse_id,
                    quantity: $line->quantity,
                    variantId: $line->variant_id,
                    referenceNumber: $invoice->invoice_number,
                    referenceId: $invoice->id
                );
            }
        }
    }

    private function handleZatcaSubmission(Invoice $invoice): void
    {
        if (!$invoice->requiresCompliance()) {
            return;
        }

        try {
            $result = $this->compliPayClient->submitInvoice($invoice);

            $updateData = [
                'compliance_status'       => $result->status,
                'compliance_uuid'         => $result->uuid,
                'compliance_hash'         => $result->hash,
                'compliance_qr_code'      => $result->qrCode,
                'compliance_response'     => $result->response,
                'compliance_submitted_at' => now(),
            ];

            if ($result->isRejected()) {
                $errorSummary = !empty($result->errors)
                    ? implode('; ', array_map(
                        fn($e) => is_array($e) ? ($e['message'] ?? json_encode($e)) : (string) $e,
                        $result->errors
                    ))
                    : ($result->message ?? 'Unknown error');

                $updateData['compliance_notes'] = 'ZATCA rejected: ' . $errorSummary;
                $invoice->update($updateData);

                $this->logWarning('Invoice sent but ZATCA rejected', [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'errors'         => $result->errors,
                    'message'        => $result->message,
                ]);

                return;
            }

            $invoice->update($updateData);
        } catch (ConnectionException $e) {
            $this->logWarning('ZATCA connection failed, scheduling retry', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            $invoice->update([
                'compliance_status'   => Invoice::COMPLIANCE_PENDING,
                'compliance_response' => ['error' => $e->getMessage()],
            ]);

            RetryComplianceSubmission::dispatch($invoice->id)->delay(now()->addMinutes(5));
        } catch (\Exception $e) {
            $invoice->update([
                'compliance_status'   => Invoice::COMPLIANCE_REJECTED,
                'compliance_response' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    private function notifyCustomer(Invoice $invoice): void
    {
        // B2B invoices must be ZATCA-cleared before notifying; deferred to ZatcaWebhookController.
        if ($invoice->invoice_type === Invoice::TYPE_STANDARD) {
            return;
        }

        if ($invoice->customer && $invoice->customer->email) {
            $invoice->customer->notify(new \App\Notifications\Sales\InvoiceSentNotification($invoice));
        }
    }

    private function accrueRebates(Invoice $invoice): void
    {
        try {
            $this->rebateAccrualService->accrueForInvoice($invoice);
        } catch (\Throwable $e) {
            $this->logWarning('Rebate accrual failed for invoice', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function trackUserEvent(Invoice $invoice): void
    {
        try {
            $this->userEventService->track(
                UserEvent::INVOICE_SENT,
                ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number],
                auth('api')->id(),
                $invoice->organization_id,
            );
        } catch (\Throwable $e) {
            $this->logWarning('Event tracking failed', [
                'event' => UserEvent::INVOICE_SENT,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
