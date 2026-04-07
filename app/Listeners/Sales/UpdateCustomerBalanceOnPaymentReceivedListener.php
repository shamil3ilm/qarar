<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Events\Sales\PaymentReceived;
use App\Services\Core\CacheService;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateCustomerBalanceOnPaymentReceivedListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(PaymentReceived $event): void
    {
        $customer = $event->payment->customer;

        if (!$customer) {
            return;
        }

        $customer->updateOutstandingBalance();

        // Bust the customer balance cache so the next read reflects the new balance.
        $orgId     = (int) $event->payment->organization_id;
        $contactId = (int) $customer->id;

        /** @var CacheService $cache */
        $cache = app(CacheService::class);
        $cache->bustCustomerBalance($orgId, $contactId);
    }
}
