<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Core\UserEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrackUserEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Maximum number of attempts before the job is marked as failed. */
    public int $tries = 3;

    public function __construct(
        private readonly string $eventType,
        private readonly array $payload,
        private readonly ?int $userId,
        private readonly ?int $organizationId,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
    ) {
        $this->onQueue('user-events');
    }

    public function handle(): void
    {
        UserEvent::withoutGlobalScopes()->create([
            'event_type'      => $this->eventType,
            'payload'         => $this->payload,
            'user_id'         => $this->userId,
            'organization_id' => $this->organizationId,
            'ip_address'      => $this->ipAddress,
            'user_agent'      => $this->userAgent,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('TrackUserEvent job failed', [
            'event_type'      => $this->eventType,
            'user_id'         => $this->userId,
            'organization_id' => $this->organizationId,
            'error'           => $exception->getMessage(),
        ]);
    }
}
