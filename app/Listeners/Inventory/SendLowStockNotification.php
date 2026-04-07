<?php

declare(strict_types=1);

namespace App\Listeners\Inventory;

use App\Events\Inventory\LowStockAlert;
use App\Models\User;
use App\Notifications\Inventory\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendLowStockNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(LowStockAlert $event): void
    {
        // withoutGlobalScopes() is required here — this listener runs on the queue
        // where there is no authenticated user, so tenant global scopes would return 0 rows.
        $users = User::withoutGlobalScopes()
            ->whereHas('roles', function ($query) {
                $query->whereIn('slug', ['inventory-manager', 'purchasing-manager', 'admin']);
            })
            ->where('organization_id', $event->product->organization_id)
            ->where('is_active', true)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new LowStockNotification($event));
    }
}
