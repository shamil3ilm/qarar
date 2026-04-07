<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Events\Accounting\JournalEntryPosted;
use App\Services\Accounting\ParallelLedgerService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * After a journal entry is posted, fan it out to all active parallel ledgers.
 *
 * Queued so the main transaction completes before parallel posting begins.
 */
class FanOutToParallelLedgers implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly ParallelLedgerService $ledgerService,
    ) {}

    public function handle(JournalEntryPosted $event): void
    {
        $this->ledgerService->fanOut($event->journalEntry);
    }
}
