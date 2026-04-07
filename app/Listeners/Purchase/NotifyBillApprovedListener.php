<?php

declare(strict_types=1);

namespace App\Listeners\Purchase;

use App\Events\Purchase\BillApproved;
use App\Models\User;
use App\Services\Core\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyBillApprovedListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notificationService) {}

    public function handle(BillApproved $event): void
    {
        $bill = $event->bill;

        if (!$bill->created_by) {
            return;
        }

        $user = User::find($bill->created_by);
        if (!$user) {
            return;
        }

        $this->notificationService->send(
            $user,
            'bill_approved',
            "Bill #{$bill->bill_number} has been approved",
            "Bill #{$bill->bill_number} has been approved",
            null,
            null,
            null,
            [
                'bill_id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'total' => $bill->total,
                'supplier_name' => $bill->supplier?->name,
            ]
        );
    }
}
