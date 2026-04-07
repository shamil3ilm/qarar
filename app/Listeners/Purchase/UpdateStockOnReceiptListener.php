<?php

declare(strict_types=1);

namespace App\Listeners\Purchase;

use App\Events\Purchase\PurchaseOrderReceived;
use App\Services\Core\NotificationService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateStockOnReceiptListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(protected NotificationService $notificationService) {}

    public function handle(PurchaseOrderReceived $event): void
    {
        $purchaseOrder = $event->purchaseOrder;

        // Notify the PO creator and warehouse team
        $recipients = User::withoutGlobalScopes()
            ->where('organization_id', $purchaseOrder->organization_id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'manager']))
            ->pluck('id')
            ->toArray();

        if ($purchaseOrder->created_by) {
            $recipients[] = $purchaseOrder->created_by;
        }

        $message = $event->isFullyReceived
            ? "Purchase Order #{$purchaseOrder->order_number} has been fully received"
            : "Partial receipt recorded for Purchase Order #{$purchaseOrder->order_number}";

        $userModels = User::withoutGlobalScopes()
            ->whereIn('id', array_unique($recipients))
            ->get()
            ->keyBy('id');

        foreach (array_unique($recipients) as $userId) {
            $user = $userModels->get($userId);
            if (!$user) {
                continue;
            }
            $this->notificationService->send(
                $user,
                'po_received',
                'Purchase Order Received',
                $message,
                null,
                null,
                null,
                [
                    'purchase_order_id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                    'is_fully_received' => $event->isFullyReceived,
                    'received_quantities' => $event->receivedQuantities,
                ]
            );
        }
    }
}
