<?php

declare(strict_types=1);

namespace App\Events\HR;

use App\Events\Concerns\HasDomainEventProperties;
use App\Events\Contracts\DomainEvent;
use App\Models\HR\Payslip;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayslipGenerated implements DomainEvent
{
    use Dispatchable, HasDomainEventProperties, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Payslip $payslip
    ) {
        $this->initDomainEvent();
    }

    public function organizationId(): int
    {
        return $this->payslip->organization_id;
    }
}
