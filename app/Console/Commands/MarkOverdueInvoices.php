<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sales\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark sent/partial invoices as overdue when their due date has passed';

    public function handle(InvoiceService $invoiceService): int
    {
        try {
            $count = $invoiceService->markOverdueInvoices();

            $this->info("Marked {$count} invoice(s) as overdue.");

            Log::info("invoices:mark-overdue: {$count} invoices updated.");
        } catch (\Throwable $e) {
            $this->error("Failed to mark overdue invoices: {$e->getMessage()}");
            Log::error('invoices:mark-overdue failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
