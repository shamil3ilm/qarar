<?php

declare(strict_types=1);

namespace App\Listeners\Manufacturing;

use App\Events\Manufacturing\WorkOrderStarted;
use App\Services\Core\NotificationService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyWorkOrderStartedListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notificationService) {}

    public function handle(WorkOrderStarted $event): void
    {
        $workOrder = $event->workOrder;

        $recipients = collect();

        // Notify the assigned operator
        if ($workOrder->assigned_to) {
            $recipients->push($workOrder->assigned_to);
        }

        // Notify the creator
        if ($workOrder->created_by) {
            $recipients->push($workOrder->created_by);
        }

        foreach ($recipients->unique() as $userId) {
            $user = User::find($userId);
            if (!$user) {
                continue;
            }
            $this->notificationService->send(
                $user,
                'work_order_started',
                "Work Order #{$workOrder->work_order_number} has started",
                "Work Order #{$workOrder->work_order_number} has started",
                null,
                null,
                null,
                [
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'product_name' => $workOrder->product?->name,
                    'quantity' => $workOrder->quantity,
                ]
            );
        }
    }
}
