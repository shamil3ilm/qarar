<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Events\Sales\InvoicePosted;
use App\Services\Core\CacheService;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateCustomerBalanceOnInvoicePostedListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(InvoicePosted $event): void
    {
        $customer = $event->invoice->customer;

        if (!$customer) {
            return;
        }

        $customer->updateOutstandingBalance();

        // Bust the customer balance cache so the next read reflects the new balance.
        $orgId     = (int) $event->invoice->organization_id;
        $contactId = (int) $customer->id;

        /** @var CacheService $cache */
        $cache = app(CacheService::class);
        $cache->bustCustomerBalance($orgId, $contactId);
    }
}
