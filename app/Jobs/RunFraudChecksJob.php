<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Fraud\FraudRuleEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunFraudChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Fire-and-forget: only one attempt. */
    public int $tries = 1;

    public function __construct(
        private readonly string $entityType,
        private readonly int    $entityId,
        private readonly array  $entityData,
        private readonly int    $organizationId,
        private readonly ?int   $userId = null,
    ) {
        $this->onQueue('fraud-checks');
    }

    public function handle(FraudRuleEngine $engine): void
    {
        $data = array_merge($this->entityData, [
            'id'      => $this->entityId,
            'user_id' => $this->userId,
        ]);

        $result = $engine->evaluate($this->entityType, $data, $this->organizationId);

        if ($result->flagged) {
            Log::info('Fraud check triggered alerts', [
                'entity_type'      => $this->entityType,
                'entity_id'        => $this->entityId,
                'organization_id'  => $this->organizationId,
                'total_score'      => $result->totalScore,
                'highest_severity' => $result->highestSeverity,
                'should_block'     => $result->shouldBlock,
                'triggered_rules'  => count($result->triggeredRules),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunFraudChecksJob failed', [
            'entity_type'     => $this->entityType,
            'entity_id'       => $this->entityId,
            'organization_id' => $this->organizationId,
            'error'           => $exception->getMessage(),
        ]);
    }
}
