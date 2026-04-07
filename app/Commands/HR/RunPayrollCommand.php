<?php

declare(strict_types=1);

namespace App\Commands\HR;

use App\Commands\Contracts\Command;

final readonly class RunPayrollCommand implements Command
{
    public function __construct(
        public readonly int  $organizationId,
        public readonly int  $payrollPeriodId,
        public readonly int  $initiatedByUserId,
        public readonly bool $dryRun = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            organizationId:    (int) $data['organization_id'],
            payrollPeriodId:   (int) $data['payroll_period_id'],
            initiatedByUserId: (int) ($data['initiated_by_user_id'] ?? auth()->id()),
            dryRun:            (bool) ($data['dry_run'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id'      => $this->organizationId,
            'payroll_period_id'    => $this->payrollPeriodId,
            'initiated_by_user_id' => $this->initiatedByUserId,
            'dry_run'              => $this->dryRun,
        ];
    }
}
