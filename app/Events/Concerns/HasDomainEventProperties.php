<?php

declare(strict_types=1);

namespace App\Events\Concerns;

use Illuminate\Support\Str;

trait HasDomainEventProperties
{
    private readonly string $occurredAt;
    private readonly string $correlationId;

    private function initDomainEvent(): void
    {
        $this->occurredAt    = now()->toIso8601String();
        $this->correlationId = Str::uuid()->toString();
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function eventVersion(): string
    {
        return 'v1';
    }
}
