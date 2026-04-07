<?php

declare(strict_types=1);

namespace App\Actions\Accounting;

use App\Actions\Contracts\Action;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalService;
use InvalidArgumentException;

class PostJournalEntryAction implements Action
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    public function execute(array $payload): JournalEntry
    {
        if (empty($payload['journal_entry_id'])) {
            throw new InvalidArgumentException('journal_entry_id is required.');
        }

        $journalEntry = JournalEntry::findOrFail($payload['journal_entry_id']);

        $this->journalService->postEntry($journalEntry);

        return $journalEntry->fresh();
    }
}
