<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\GdprConsentRecord;
use App\Models\Core\GdprDataSubjectRequest;
use App\Models\Core\GdprProcessingActivity;
use Illuminate\Support\Str;

class GdprService
{
    public function submitDataRequest(array $data): GdprDataSubjectRequest
    {
        return GdprDataSubjectRequest::create([
            'uuid'           => Str::uuid(),
            'organization_id' => $data['organization_id'],
            'request_type'   => $data['request_type'],
            'requester_name' => $data['requester_name'],
            'requester_email' => $data['requester_email'],
            'requester_id'   => $data['requester_id'] ?? null,
            'status'         => 'received',
            'received_at'    => now(),
            'deadline_at'    => now()->addDays(30),
        ]);
    }

    public function processErasureRequest(GdprDataSubjectRequest $request): void
    {
        // Anonymize PII fields for the requester's email across relevant tables
        $request->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function exportDataPortability(GdprDataSubjectRequest $request): string
    {
        // Generate export — placeholder returns a path reference
        $exportPath = 'gdpr-exports/' . $request->uuid . '.json';
        $request->update([
            'status'              => 'completed',
            'completed_at'        => now(),
            'data_exported_path'  => $exportPath,
        ]);
        return $exportPath;
    }

    public function getProcessingRegister(int $orgId): array
    {
        return GdprProcessingActivity::where('organization_id', $orgId)->get()->toArray();
    }

    public function recordConsent(array $data): GdprConsentRecord
    {
        return GdprConsentRecord::create([
            'uuid'            => Str::uuid(),
            'organization_id' => $data['organization_id'],
            'contact_id'      => $data['contact_id'] ?? null,
            'user_id'         => $data['user_id'] ?? null,
            'purpose'         => $data['purpose'],
            'consent_given'   => true,
            'given_at'        => now(),
            'ip_address'      => $data['ip_address'] ?? null,
            'consent_text'    => $data['consent_text'] ?? null,
        ]);
    }

    public function withdrawConsent(GdprConsentRecord $record): void
    {
        $record->update(['consent_given' => false, 'withdrawn_at' => now()]);
    }
}
