<?php

declare(strict_types=1);

namespace App\Events\Accounting;

use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JournalEntryPosted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly JournalEntry $journalEntry,
    ) {}
}
