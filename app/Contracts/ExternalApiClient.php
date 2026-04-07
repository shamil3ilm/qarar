<?php

declare(strict_types=1);

namespace App\Contracts;

interface ExternalApiClient
{
    /** Submit an invoice for compliance processing */
    public function submitInvoice(array $payload): array;

    /** Get the compliance status of a previously submitted invoice */
    public function getStatus(string $referenceId): array;

    /** Human-readable version identifier, e.g. 'v1' */
    public function clientVersion(): string;
}
