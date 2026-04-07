<?php

declare(strict_types=1);

namespace App\Events\Contracts;

interface DomainEvent
{
    /** The organization this event belongs to */
    public function organizationId(): int;

    /** ISO-8601 timestamp when event occurred */
    public function occurredAt(): string;

    /** Schema version of this event payload (increment when fields change) */
    public function eventVersion(): string;

    /** Unique correlation ID for tracing chains of events */
    public function correlationId(): string;
}
