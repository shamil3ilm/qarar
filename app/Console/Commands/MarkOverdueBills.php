<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Purchase\BillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkOverdueBills extends Command
{
    protected $signature = 'bills:mark-overdue';

    protected $description = 'Mark approved/partial bills as overdue when their due date has passed';

    public function handle(BillService $billService): int
    {
        try {
            $count = $billService->markOverdueBills();

            $this->info("Marked {$count} bill(s) as overdue.");

            Log::info("bills:mark-overdue: {$count} bills updated.");
        } catch (\Throwable $e) {
            $this->error("Failed to mark overdue bills: {$e->getMessage()}");
            Log::error('bills:mark-overdue failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
