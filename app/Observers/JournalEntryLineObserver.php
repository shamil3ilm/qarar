<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateAccountBalanceSnapshotJob;
use App\Models\Accounting\JournalEntryLine;

class JournalEntryLineObserver
{
    /**
     * Dispatch a snapshot-update job whenever a journal entry line is saved.
     * The job checks on its own that the parent entry is posted.
     */
    public function created(JournalEntryLine $line): void
    {
        $this->dispatchSnapshot($line);
    }

    public function updated(JournalEntryLine $line): void
    {
        $this->dispatchSnapshot($line);
    }

    public function deleted(JournalEntryLine $line): void
    {
        $this->dispatchSnapshot($line);
    }

    private function dispatchSnapshot(JournalEntryLine $line): void
    {
        // Resolve organization_id from the parent journal entry to avoid extra queries
        // when the relationship is already loaded; fall back to a direct query otherwise.
        $organizationId = $line->relationLoaded('journalEntry')
            ? $line->journalEntry->organization_id
            : $line->journalEntry()->value('organization_id');

        if ($organizationId === null) {
            return;
        }

        UpdateAccountBalanceSnapshotJob::dispatch($organizationId, $line->account_id);
    }
}
