<?php

declare(strict_types=1);

namespace App\Events\Sales;

use App\Events\Concerns\HasDomainEventProperties;
use App\Events\Contracts\DomainEvent;
use App\Models\Sales\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePosted implements DomainEvent
{
    use Dispatchable, HasDomainEventProperties, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {
        $this->initDomainEvent();
    }

    public function organizationId(): int
    {
        return $this->invoice->organization_id;
    }
}
