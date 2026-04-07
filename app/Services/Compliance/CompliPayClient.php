<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Sales\Invoice;
use App\Services\Compliance\ComplianceResult;
use App\Services\Compliance\ZatcaInvoiceTransformer;
use App\Traits\LogsExternalApiCalls;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for ZATCA compliance integration.
 *
 * Communicates with the ZATCA middleware project for e-invoicing
 * compliance in Saudi Arabia (and other GCC authorities via future expansion).
 */
class CompliPayClient
{
    use LogsExternalApiCalls;

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private bool $enabled;
    private int $retryTimes;
    private int $retrySleep;

    public function __construct()
    {
        $this->baseUrl        = rtrim((string) config('zatca-integration.url', ''), '/');
        $this->apiKey         = (string) config('zatca-integration.api_key', '');
        $this->timeout        = (int) config('zatca-integration.timeout', 30);
        $this->enabled        = (bool) config('zatca-integration.enabled', true);
        $this->retryTimes     = (int) config('zatca-integration.retry.times', 3);
        $this->retrySleep     = (int) config('zatca-integration.retry.sleep', 1000);
        $this->apiServiceName = 'CompliPayClient';
    }

    /**
     * Submit an invoice for compliance processing via the ZATCA pipeline.
     */
    public function submitInvoice(Invoice $invoice): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult([
                'status' => 'not_applicable',
                'message' => 'Compliance integration disabled',
            ]);
        }

        if ($this->circuitBreaker()->isOpen('zatca')) {
            Log::warning('CompliPayClient: circuit breaker open, skipping ZATCA submission', [
                'invoice_id' => $invoice->id,
            ]);

            return new ComplianceResult([
                'status'  => 'pending',
                'message' => 'Circuit breaker open — ZATCA temporarily unavailable',
            ]);
        }

        try {
            $payload = ZatcaInvoiceTransformer::transform($invoice);
            $url     = $this->baseUrl . '/pipeline/submit';

            $response = $this->loggedApiCall(
                'POST',
                $url,
                fn () => $this->client()->post('/pipeline/submit', $payload),
                null,
                $payload,
            );

            if ($response->failed()) {
                Log::error('ZATCA submission failed', [
                    'invoice_id' => $invoice->id,
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                ]);

                return new ComplianceResult([
                    'status'  => 'rejected',
                    'message' => $response->json('message', 'Submission failed'),
                    'errors'  => $response->json('errors', []),
                ]);
            }

            $data        = $response->json();
            $invoiceData = $data['data']['invoice'] ?? [];

            Log::info('ZATCA submission successful', [
                'invoice_id'      => $invoice->id,
                'compliance_uuid' => $invoiceData['id'] ?? null,
            ]);

            $this->circuitBreaker()->recordSuccess('zatca');

            return new ComplianceResult([
                'status'   => $invoiceData['status'] ?? ($data['status'] ?? 'submitted'),
                'uuid'     => $invoiceData['id'] ?? null,
                'hash'     => $invoiceData['hash'] ?? null,
                'qr_code'  => $invoiceData['qr_code'] ?? null,
                'message'  => $data['message'] ?? 'Submitted successfully',
                'response' => $data,
            ]);

        } catch (ConnectionException $e) {
            $this->circuitBreaker()->recordFailure('zatca');

            Log::error('ZATCA connection error', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            return new ComplianceResult([
                'status'  => 'pending',
                'message' => 'Connection error: ' . $e->getMessage(),
            ]);

        } catch (\Exception $e) {
            $this->circuitBreaker()->recordFailure('zatca');

            Log::error('ZATCA submission exception', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            return new ComplianceResult([
                'status'  => 'rejected',
                'message' => 'Submission error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get compliance status for a submitted invoice.
     */
    public function getStatus(string $complianceUuid): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult(['status' => 'not_applicable']);
        }

        if ($this->circuitBreaker()->isOpen('zatca')) {
            Log::warning('CompliPayClient: circuit breaker open, skipping ZATCA status check', [
                'compliance_uuid' => $complianceUuid,
            ]);

            return new ComplianceResult([
                'status'  => 'error',
                'message' => 'Circuit breaker open — ZATCA temporarily unavailable',
            ]);
        }

        try {
            $url      = $this->baseUrl . "/pipeline/status/{$complianceUuid}";
            $response = $this->loggedApiCall(
                'GET',
                $url,
                fn () => $this->client()->get("/pipeline/status/{$complianceUuid}"),
            );

            if ($response->failed()) {
                return new ComplianceResult([
                    'status'  => 'error',
                    'message' => 'Failed to retrieve status',
                ]);
            }

            $data        = $response->json();
            $invoiceData = $data['data']['invoice'] ?? $data['data'] ?? [];

            $this->circuitBreaker()->recordSuccess('zatca');

            return new ComplianceResult([
                'status'   => $invoiceData['status'] ?? ($data['status'] ?? 'unknown'),
                'uuid'     => $invoiceData['id'] ?? $complianceUuid,
                'hash'     => $invoiceData['hash'] ?? null,
                'qr_code'  => $invoiceData['qr_code'] ?? null,
                'message'  => $data['message'] ?? null,
                'response' => $data,
            ]);

        } catch (\Exception $e) {
            $this->circuitBreaker()->recordFailure('zatca');

            return new ComplianceResult([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate invoice data without submitting to ZATCA.
     */
    public function validate(Invoice $invoice): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult(['status' => 'valid']);
        }

        try {
            $payload = ZatcaInvoiceTransformer::transform($invoice);
            $payload['auto_submit'] = false;

            $response = $this->client()
                ->post('/pipeline/submit', $payload);

            if ($response->failed()) {
                return new ComplianceResult([
                    'status' => 'invalid',
                    'message' => $response->json('message', 'Validation failed'),
                    'errors' => $response->json('errors', []),
                ]);
            }

            return new ComplianceResult($response->json());

        } catch (\Exception $e) {
            return new ComplianceResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel/void a submitted invoice.
     */
    public function cancelInvoice(string $complianceUuid, string $reason): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult(['status' => 'not_applicable']);
        }

        try {
            $response = $this->client()
                ->post("/pipeline/{$complianceUuid}/cancel", [
                    'reason' => $reason,
                ]);

            if ($response->failed()) {
                return new ComplianceResult([
                    'status' => 'error',
                    'message' => 'Cancellation failed: ' . $response->json('message'),
                ]);
            }

            return new ComplianceResult($response->json());

        } catch (\Exception $e) {
            return new ComplianceResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Report invoice (for ZATCA Phase 2 reporting).
     */
    public function reportInvoice(Invoice $invoice): ComplianceResult
    {
        if (!$this->enabled || !$invoice->compliance_uuid) {
            return new ComplianceResult(['status' => 'not_applicable']);
        }

        try {
            $response = $this->client()
                ->post("/pipeline/{$invoice->compliance_uuid}/report");

            return new ComplianceResult($response->json());

        } catch (\Exception $e) {
            return new ComplianceResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get QR code for an invoice.
     */
    public function getQrCode(string $complianceUuid): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $response = $this->client()
                ->get("/pipeline/{$complianceUuid}/qr-code");

            if ($response->successful()) {
                return $response->json('qr_code') ?? $response->json('data.qr_code');
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Register a new device/EGS (for ZATCA).
     */
    public function registerDevice(array $deviceData): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult(['status' => 'not_applicable']);
        }

        try {
            $response = $this->client()
                ->post('/devices/register', $deviceData);

            return new ComplianceResult($response->json());

        } catch (\Exception $e) {
            return new ComplianceResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Submit a credit note.
     */
    public function submitCreditNote(Invoice $creditNote): ComplianceResult
    {
        if (!$creditNote->isCreditNote()) {
            throw new \InvalidArgumentException('Invoice is not a credit note.');
        }

        return $this->submitInvoice($creditNote);
    }

    // -----------------------------------------------------------------------
    // Onboarding endpoints
    // -----------------------------------------------------------------------

    /**
     * Request a Compliance CSID (CCSID) for a ZATCA branch.
     */
    public function requestCcsid(string $zatcaBranchId, string $otp, array $csrData): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult(['status' => 'not_applicable']);
        }

        try {
            $response = $this->client()
                ->post('/onboarding/ccsid', [
                    'branch_id' => $zatcaBranchId,
                    'otp' => $otp,
                    'csr' => $csrData,
                ]);

            if ($response->failed()) {
                return new ComplianceResult([
                    'status' => 'error',
                    'message' => $response->json('message', 'CCSID request failed'),
                    'errors' => $response->json('errors', []),
                ]);
            }

            return new ComplianceResult($response->json());

        } catch (\Exception $e) {
            return new ComplianceResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Run the compliance check for a ZATCA branch.
     */
    public function runComplianceCheck(string $zatcaBranchId): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult(['status' => 'not_applicable']);
        }

        try {
            $response = $this->client()
                ->post('/onboarding/compliance-check', [
                    'branch_id' => $zatcaBranchId,
                ]);

            if ($response->failed()) {
                return new ComplianceResult([
                    'status' => 'error',
                    'message' => $response->json('message', 'Compliance check failed'),
                    'errors' => $response->json('errors', []),
                ]);
            }

            return new ComplianceResult($response->json());

        } catch (\Exception $e) {
            return new ComplianceResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Request a Production CSID (PCSID) for a ZATCA branch.
     */
    public function requestPcsid(string $zatcaBranchId): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult(['status' => 'not_applicable']);
        }

        try {
            $response = $this->client()
                ->post('/onboarding/pcsid', [
                    'branch_id' => $zatcaBranchId,
                ]);

            if ($response->failed()) {
                return new ComplianceResult([
                    'status' => 'error',
                    'message' => $response->json('message', 'PCSID request failed'),
                    'errors' => $response->json('errors', []),
                ]);
            }

            return new ComplianceResult($response->json());

        } catch (\Exception $e) {
            return new ComplianceResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the current onboarding status for a ZATCA branch.
     */
    public function getOnboardingStatus(string $zatcaBranchId): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult(['status' => 'not_applicable']);
        }

        try {
            $response = $this->client()
                ->get("/onboarding/status/{$zatcaBranchId}");

            if ($response->failed()) {
                return new ComplianceResult([
                    'status' => 'error',
                    'message' => $response->json('message', 'Failed to retrieve onboarding status'),
                ]);
            }

            return new ComplianceResult($response->json());

        } catch (\Exception $e) {
            return new ComplianceResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a webhook for receiving ZATCA event callbacks.
     */
    public function registerWebhook(string $callbackUrl, array $events, string $secret): ComplianceResult
    {
        if (!$this->enabled) {
            return new ComplianceResult(['status' => 'not_applicable']);
        }

        try {
            $response = $this->client()
                ->post('/webhooks', [
                    'callback_url' => $callbackUrl,
                    'events' => $events,
                    'secret' => $secret,
                ]);

            if ($response->failed()) {
                return new ComplianceResult([
                    'status' => 'error',
                    'message' => $response->json('message', 'Webhook registration failed'),
                    'errors' => $response->json('errors', []),
                ]);
            }

            return new ComplianceResult($response->json());

        } catch (\Exception $e) {
            return new ComplianceResult([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the circuit breaker from the container.
     */
    private function circuitBreaker(): CircuitBreaker
    {
        return app(CircuitBreaker::class);
    }

    /**
     * Get HTTP client with authentication and exponential-backoff retry logic.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->retry(
                $this->retryTimes,
                function (int $attempt, \Throwable $exception): int {
                    // Exponential backoff: 1 s, 2 s, 4 s … with ±20 % jitter
                    $base = 1000 * (2 ** ($attempt - 1));
                    return (int) ($base * (0.8 + lcg_value() * 0.4));
                },
                fn (\Throwable $exception, PendingRequest $request): bool =>
                    $exception instanceof ConnectionException
                    || (
                        isset($exception->response)
                        && in_array($exception->response->status(), [429, 500, 502, 503], true)
                    )
            );
    }
}
