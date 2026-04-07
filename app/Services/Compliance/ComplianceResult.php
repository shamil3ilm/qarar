<?php

declare(strict_types=1);

namespace App\Services\Compliance;

/**
 * Compliance submission result.
 */
class ComplianceResult
{
    public string $status;
    public ?string $uuid;
    public ?string $hash;
    public ?string $qrCode;
    public ?string $message;
    public array $errors;
    public array $response;

    public function __construct(array $data)
    {
        $this->status = $data['status'] ?? 'unknown';
        $this->uuid = $data['uuid'] ?? $data['compliance_uuid'] ?? null;
        $this->hash = $data['hash'] ?? $data['invoice_hash'] ?? null;
        $this->qrCode = $data['qr_code'] ?? $data['qrCode'] ?? null;
        $this->message = $data['message'] ?? null;
        $this->errors = $data['errors'] ?? [];
        $this->response = $data;
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['submitted', 'cleared', 'reported', 'valid'], true);
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
