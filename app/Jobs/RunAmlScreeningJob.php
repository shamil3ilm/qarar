<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Sales\Contact;
use App\Services\Aml\AmlMonitoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAmlScreeningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int $contactId,
        private readonly int $organizationId,
    ) {
        $this->onQueue('aml-screening');
    }

    public function handle(AmlMonitoringService $service): void
    {
        $contact = Contact::withoutGlobalScope('organization')
            ->where('id', $this->contactId)
            ->where('organization_id', $this->organizationId)
            ->first();

        if ($contact === null) {
            Log::warning('RunAmlScreeningJob: contact not found', [
                'contact_id'      => $this->contactId,
                'organization_id' => $this->organizationId,
            ]);

            return;
        }

        $service->screenContact($contact);
        $service->updateRiskScore($contact);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunAmlScreeningJob failed', [
            'contact_id'      => $this->contactId,
            'organization_id' => $this->organizationId,
            'error'           => $exception->getMessage(),
        ]);
    }
}
