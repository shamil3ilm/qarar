<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Sales\Invoice;
use App\Services\Compliance\CompliPayClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryComplianceSubmission implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600; // 1 hour

    public function __construct(
        private readonly int $invoiceId
    ) {}

    public function uniqueId(): string
    {
        return $this->invoiceId . ':' . now()->format('Y-m-d-H');
    }

    /**
     * Exponential-style back-off in seconds: 5m, 10m, 30m, 1h, 2h.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [300, 600, 1800, 3600, 7200];
    }

    public function handle(): void
    {
        // Use lockForUpdate inside a transaction to prevent two concurrent retry
        // attempts from both passing the status check and double-submitting.
        $invoice = DB::transaction(function () {
            $locked = Invoice::withoutGlobalScopes()
                ->lockForUpdate()
                ->find($this->invoiceId);

            if ($locked === null) {
                Log::warning('RetryComplianceSubmission: invoice not found, deleting job', [
                    'invoice_id' => $this->invoiceId,
                ]);
                return null;
            }

            if ($locked->compliance_status !== Invoice::COMPLIANCE_PENDING) {
                Log::info('RetryComplianceSubmission: invoice no longer pending, skipping', [
                    'invoice_id'        => $this->invoiceId,
                    'compliance_status' => $locked->compliance_status,
                ]);
                return false; // Signal "skip" without deleting
            }

            return $locked;
        });

        if ($invoice === null || $invoice === false) {
            return;
        }

        /** @var CompliPayClient $client */
        $client = app(CompliPayClient::class);
        $result = $client->submitInvoice($invoice);

        if ($result->isRejected()) {
            $invoice->compliance_status   = Invoice::COMPLIANCE_REJECTED;
            $invoice->compliance_response = ['errors' => $result->errors, 'message' => $result->message];
            $invoice->save();

            Log::warning('RetryComplianceSubmission: invoice rejected, not retrying', [
                'invoice_id' => $this->invoiceId,
                'message'    => $result->message,
            ]);

            return;
        }

        $updates = [];

        if (!empty($result->uuid)) {
            $updates['compliance_uuid'] = $result->uuid;
        }

        if (!empty($result->hash)) {
            $updates['compliance_hash'] = $result->hash;
        }

        if (!empty($result->qrCode)) {
            $updates['compliance_qr_code'] = $result->qrCode;
        }

        if ($result->isSuccessful()) {
            $statusMap = [
                'cleared'   => Invoice::COMPLIANCE_CLEARED,
                'reported'  => Invoice::COMPLIANCE_REPORTED,
                'submitted' => Invoice::COMPLIANCE_SUBMITTED,
            ];

            $updates['compliance_status'] = $statusMap[$result->status] ?? Invoice::COMPLIANCE_SUBMITTED;
            $updates['compliance_submitted_at'] = now();

            $invoice->fill($updates)->save();

            Log::info('RetryComplianceSubmission: submission successful', [
                'invoice_id'        => $this->invoiceId,
                'compliance_status' => $updates['compliance_status'],
            ]);

            return;
        }

        // Still pending (e.g. connection error) — persist any partial data then throw to trigger retry.
        if (!empty($updates)) {
            $invoice->fill($updates)->save();
        }

        throw new \RuntimeException(
            'ZATCA submission still pending after attempt: ' . ($result->message ?? 'unknown error')
        );
    }

    public function failed(\Throwable $e): void
    {
        $invoice = Invoice::withoutGlobalScopes()->find($this->invoiceId);

        if ($invoice) {
            $invoice->compliance_status   = Invoice::COMPLIANCE_REJECTED;
            $invoice->compliance_response = [
                'error'   => $e->getMessage(),
                'message' => 'Max retry attempts reached; compliance submission failed permanently',
            ];
            $invoice->save();
        }

        Log::error('RetryComplianceSubmission: all attempts exhausted', [
            'invoice_id' => $this->invoiceId,
            'error'      => $e->getMessage(),
        ]);
    }
}
