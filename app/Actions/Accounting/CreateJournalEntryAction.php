<?php

declare(strict_types=1);

namespace App\Actions\Accounting;

use App\Actions\Contracts\Action;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalService;
use InvalidArgumentException;

class CreateJournalEntryAction implements Action
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    public function execute(array $payload): JournalEntry
    {
        if (empty($payload['organization_id'])) {
            throw new InvalidArgumentException('organization_id is required.');
        }

        if (empty($payload['entry_date'])) {
            throw new InvalidArgumentException('entry_date is required.');
        }

        if (!isset($payload['lines']) || !is_array($payload['lines'])) {
            throw new InvalidArgumentException('lines must be an array.');
        }

        $lines = $payload['lines'];

        return $this->journalService->create($payload, $lines);
    }
}
