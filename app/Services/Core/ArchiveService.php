<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Accounting\JournalEntry;
use App\Models\Sales\Invoice;
use App\Models\System\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves old completed records to archive tables to keep hot tables lean.
 *
 * Archive policy (configurable via config/erp.php or env):
 *   - Journal entries: posted, older than ARCHIVE_JOURNAL_DAYS (default 365)
 *   - Invoices: paid/cancelled, older than ARCHIVE_INVOICE_DAYS (default 365)
 *   - Audit logs: older than ARCHIVE_AUDIT_DAYS (default 180)
 */
class ArchiveService
{
    public function archiveJournalEntries(int $daysOld = 365, int $batchSize = 500): int
    {
        $cutoff = Carbon::now()->subDays($daysOld);
        $archived = 0;

        JournalEntry::where('status', 'posted')
            ->where('entry_date', '<', $cutoff)
            ->chunkById($batchSize, function ($entries) use (&$archived) {
                DB::transaction(function () use ($entries, &$archived) {
                    foreach ($entries as $entry) {
                        DB::table('journal_entry_archives')->insertOrIgnore([
                            'uuid'            => $entry->uuid,
                            'organization_id' => $entry->organization_id,
                            'entry_number'    => $entry->entry_number,
                            'type'            => $entry->source_type,
                            'reference'       => $entry->reference,
                            'description'     => $entry->description,
                            'entry_date'      => $entry->entry_date,
                            'total_debit'     => $entry->total_debit,
                            'total_credit'    => $entry->total_credit,
                            'status'          => $entry->status,
                            'currency_code'   => $entry->currency_code ?? 'SAR',
                            'metadata'        => json_encode(['lines_count' => $entry->lines()->count()]),
                            'archived_at'     => now(),
                            'created_at'      => $entry->created_at,
                            'updated_at'      => now(),
                        ]);
                        $entry->forceDelete();
                        $archived++;
                    }
                });
            });

        Log::info('ArchiveService: journal entries archived', ['count' => $archived, 'days_old' => $daysOld]);

        return $archived;
    }

    public function archiveInvoices(int $daysOld = 365, int $batchSize = 500): int
    {
        $cutoff = Carbon::now()->subDays($daysOld);
        $archived = 0;

        Invoice::whereIn('status', ['paid', 'cancelled', 'void'])
            ->where('invoice_date', '<', $cutoff)
            ->chunkById($batchSize, function ($invoices) use (&$archived) {
                DB::transaction(function () use ($invoices, &$archived) {
                    foreach ($invoices as $invoice) {
                        DB::table('invoice_archives')->insertOrIgnore([
                            'uuid'            => $invoice->uuid,
                            'organization_id' => $invoice->organization_id,
                            'invoice_number'  => $invoice->invoice_number,
                            'status'          => $invoice->status,
                            'invoice_date'    => $invoice->invoice_date,
                            'due_date'        => $invoice->due_date,
                            'subtotal'        => $invoice->subtotal,
                            'tax_amount'      => $invoice->tax_amount,
                            'total'           => $invoice->total,
                            'amount_paid'     => $invoice->amount_paid,
                            'amount_due'      => $invoice->amount_due,
                            'currency_code'   => $invoice->currency_code ?? 'SAR',
                            'snapshot'        => json_encode([
                                'customer_id' => $invoice->customer_id,
                                'lines_count' => $invoice->lines()->count(),
                            ]),
                            'archived_at'     => now(),
                            'created_at'      => $invoice->created_at,
                            'updated_at'      => now(),
                        ]);
                        $invoice->forceDelete();
                        $archived++;
                    }
                });
            });

        Log::info('ArchiveService: invoices archived', ['count' => $archived, 'days_old' => $daysOld]);

        return $archived;
    }

    public function archiveAuditLogs(int $daysOld = 180, int $batchSize = 1000): int
    {
        $cutoff = Carbon::now()->subDays($daysOld);
        $archived = 0;

        AuditLog::where('created_at', '<', $cutoff)
            ->chunkById($batchSize, function ($logs) use (&$archived) {
                DB::transaction(function () use ($logs, &$archived) {
                    foreach ($logs as $log) {
                        DB::table('audit_log_archives')->insertOrIgnore([
                            'organization_id' => $log->organization_id,
                            'event'           => $log->event,
                            'auditable_type'  => $log->auditable_type,
                            'auditable_id'    => $log->auditable_id,
                            'user_id'         => $log->user_id,
                            'old_values'      => json_encode($log->old_values),
                            'new_values'      => json_encode($log->new_values),
                            'ip_address'      => $log->ip_address,
                            'archived_at'     => now(),
                            'created_at'      => $log->created_at,
                            'updated_at'      => now(),
                        ]);
                        $log->forceDelete();
                        $archived++;
                    }
                });
            });

        Log::info('ArchiveService: audit logs archived', ['count' => $archived, 'days_old' => $daysOld]);

        return $archived;
    }

    /**
     * Run all archive routines and return counts per entity type.
     *
     * @param  array{journal_days?: int, invoice_days?: int, audit_days?: int, batch_size?: int}  $options
     * @return array{journal_entries: int, invoices: int, audit_logs: int}
     */
    public function runAll(array $options = []): array
    {
        return [
            'journal_entries' => $this->archiveJournalEntries(
                $options['journal_days'] ?? 365,
                $options['batch_size'] ?? 500,
            ),
            'invoices'        => $this->archiveInvoices(
                $options['invoice_days'] ?? 365,
                $options['batch_size'] ?? 500,
            ),
            'audit_logs'      => $this->archiveAuditLogs(
                $options['audit_days'] ?? 180,
                $options['batch_size'] ?? 1000,
            ),
        ];
    }
}
