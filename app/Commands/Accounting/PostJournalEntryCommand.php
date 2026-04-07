<?php

declare(strict_types=1);

namespace App\Commands\Accounting;

use App\Commands\Contracts\Command;

final readonly class PostJournalEntryCommand implements Command
{
    public function __construct(
        public readonly int     $organizationId,
        public readonly int     $journalEntryId,
        public readonly int     $postedByUserId,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            organizationId:  (int) $data['organization_id'],
            journalEntryId:  (int) $data['journal_entry_id'],
            postedByUserId:  (int) ($data['posted_by_user_id'] ?? auth()->id()),
            notes:           $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id'  => $this->organizationId,
            'journal_entry_id' => $this->journalEntryId,
            'posted_by_user_id' => $this->postedByUserId,
            'notes'            => $this->notes,
        ];
    }
}
