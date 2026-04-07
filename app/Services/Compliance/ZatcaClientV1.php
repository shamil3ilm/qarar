<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Contracts\ExternalApiClient;

/**
 * ZATCA compliance client — version 1 (CompliPay gateway).
 * When the integration endpoint changes, create ZatcaClientV2 implementing the same interface
 * without touching any code that depends on ExternalApiClient.
 *
 * This adapter bridges the array-based ExternalApiClient contract to the typed
 * CompliPayClient, serialising ComplianceResult responses back into plain arrays.
 */
final class ZatcaClientV1 implements ExternalApiClient
{
    public function __construct(
        private readonly CompliPayClient $client,
    ) {}

    public function submitInvoice(array $payload): array
    {
        // CompliPayClient expects an Invoice model; callers using the
        // ExternalApiClient contract pass the already-fetched Invoice instance
        // under the 'invoice' key so the adapter can delegate correctly.
        /** @var \App\Models\Sales\Invoice $invoice */
        $invoice = $payload['invoice'];
        $result  = $this->client->submitInvoice($invoice);

        return (array) $result->response;
    }

    public function getStatus(string $referenceId): array
    {
        $result = $this->client->getStatus($referenceId);

        return (array) $result->response;
    }

    public function clientVersion(): string
    {
        return 'v1';
    }
}
