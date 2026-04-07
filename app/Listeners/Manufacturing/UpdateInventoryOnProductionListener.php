<?php

declare(strict_types=1);

namespace App\Listeners\Manufacturing;

use App\Events\Manufacturing\ProductionRecorded;
use App\Services\Core\NotificationService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateInventoryOnProductionListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(protected NotificationService $notificationService) {}

    public function handle(ProductionRecorded $event): void
    {
        $workOrder = $event->workOrder;
        $productionLog = $event->productionLog;

        // Notify supervisors when production milestone reached
        $quantityProduced = $workOrder->quantity_produced ?? 0;
        $quantityOrdered = $workOrder->quantity ?? 0;

        if ($quantityOrdered > 0 && $quantityProduced >= $quantityOrdered) {
            $recipients = User::withoutGlobalScopes()
                ->where('organization_id', $workOrder->organization_id)
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'manager']))
                ->pluck('id');

            $userModels = User::withoutGlobalScopes()
                ->whereIn('id', $recipients->toArray())
                ->get()
                ->keyBy('id');

            foreach ($recipients as $userId) {
                $user = $userModels->get($userId);
                if (!$user) {
                    continue;
                }
                $this->notificationService->send(
                    $user,
                    'production_complete',
                    'Production Complete',
                    "Work Order #{$workOrder->work_order_number} production is complete",
                    null,
                    null,
                    null,
                    [
                        'work_order_id' => $workOrder->id,
                        'work_order_number' => $workOrder->work_order_number,
                        'quantity_produced' => $quantityProduced,
                    ]
                );
            }
        }
    }
}
