<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Aml\AmlSuspiciousActivity;
use App\Models\Aml\AmlTransactionFlag;
use App\Services\Aml\AmlMonitoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAmlEscalationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Minimum number of flags required to auto-create a SAR. */
    private const SAR_THRESHOLD = 3;

    public function __construct(
        private readonly string $transactionType,
        private readonly int    $transactionId,
        private readonly int    $organizationId,
    ) {
        $this->onQueue('aml-escalation');
    }

    public function handle(AmlMonitoringService $service): void
    {
        $flags = AmlTransactionFlag::withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->where('transaction_type', $this->transactionType)
            ->where('transaction_id', $this->transactionId)
            ->where('status', AmlTransactionFlag::STATUS_FLAGGED)
            ->get();

        if ($flags->count() < self::SAR_THRESHOLD) {
            return;
        }

        // Idempotency: skip if a SAR already exists for this transaction (e.g. on job retry)
        $alreadyExists = AmlSuspiciousActivity::withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->whereJsonContains('related_transaction_ids', $this->transactionId)
            ->where('activity_type', AmlTransactionFlag::STRUCTURING)
            ->exists();

        if ($alreadyExists) {
            Log::info('RunAmlEscalationJob: SAR already exists, marking flags as escalated', [
                'transaction_type' => $this->transactionType,
                'transaction_id'   => $this->transactionId,
                'organization_id'  => $this->organizationId,
            ]);

            AmlTransactionFlag::withoutGlobalScopes()
                ->where('organization_id', $this->organizationId)
                ->where('transaction_type', $this->transactionType)
                ->where('transaction_id', $this->transactionId)
                ->update(['status' => AmlTransactionFlag::STATUS_ESCALATED]);

            return;
        }

        $contactId    = $flags->whereNotNull('contact_id')->first()?->contact_id;
        $flagReasons  = $flags->pluck('flag_reason')->unique()->implode(', ');
        $transactionIds = [$this->transactionId];

        $description = sprintf(
            'Auto-generated SAR: %d AML flags detected on %s #%d. Flag reasons: %s.',
            $flags->count(),
            $this->transactionType,
            $this->transactionId,
            $flagReasons,
        );

        try {
            $service->createSar(
                organizationId: $this->organizationId,
                contactId:      $contactId ?? 0,
                activityType:   AmlTransactionFlag::STRUCTURING,
                transactionIds: $transactionIds,
                description:    $description,
            );

            // Mark flags as escalated
            AmlTransactionFlag::withoutGlobalScopes()
                ->where('organization_id', $this->organizationId)
                ->where('transaction_type', $this->transactionType)
                ->where('transaction_id', $this->transactionId)
                ->update(['status' => AmlTransactionFlag::STATUS_ESCALATED]);

            Log::info('RunAmlEscalationJob: SAR auto-created', [
                'transaction_type' => $this->transactionType,
                'transaction_id'   => $this->transactionId,
                'organization_id'  => $this->organizationId,
                'flag_count'       => $flags->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('RunAmlEscalationJob: SAR creation failed', [
                'transaction_id'  => $this->transactionId,
                'organization_id' => $this->organizationId,
                'error'           => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunAmlEscalationJob failed permanently', [
            'transaction_type' => $this->transactionType,
            'transaction_id'   => $this->transactionId,
            'organization_id'  => $this->organizationId,
            'error'            => $exception->getMessage(),
        ]);
    }
}
