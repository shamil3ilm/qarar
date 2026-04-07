<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveOldDataCommand extends Command
{
    protected $signature = 'erp:archive
        {--module=all : Module to archive (invoices, journal_entries, all)}
        {--before= : Archive records before this date (Y-m-d). Defaults to 2 years ago.}
        {--org= : Limit to specific organization_id}
        {--dry-run : Show what would be archived without doing it}';

    protected $description = 'Archive old ERP records to archive tables to keep live tables lean';

    public function handle(): int
    {
        $before = $this->option('before')
            ? Carbon::parse($this->option('before'))
            : now()->subYears(2);

        $orgId  = $this->option('org') ? (int) $this->option('org') : null;
        $dryRun = (bool) $this->option('dry-run');
        $module = $this->option('module');

        $this->info($dryRun ? '[DRY RUN] Would archive records...' : 'Archiving old records...');
        $this->info("Before: {$before->toDateString()}" . ($orgId ? ", Org: {$orgId}" : ''));

        $total = 0;

        if (in_array($module, ['invoices', 'all'], true)) {
            $total += $this->archiveTable('invoices', 'invoice_date', $before, $orgId, $dryRun);
        }

        if (in_array($module, ['journal_entries', 'all'], true)) {
            $total += $this->archiveTable('journal_entries', 'entry_date', $before, $orgId, $dryRun);
        }

        $this->info($dryRun
            ? "[DRY RUN] Would archive {$total} records total."
            : "Done. Archived {$total} records total.");

        return self::SUCCESS;
    }

    private function archiveTable(
        string $table,
        string $dateColumn,
        Carbon $before,
        ?int $orgId,
        bool $dryRun
    ): int {
        $archiveTable = $table . '_archive';
        $count        = 0;

        $query = DB::table($table)
            ->where($dateColumn, '<', $before->toDateString())
            ->whereNotNull('deleted_at'); // Only archive soft-deleted records

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        $total = $query->count();
        $this->line("  {$table}: {$total} records to archive");

        if ($dryRun || $total === 0) {
            return $total;
        }

        $query->orderBy('id')->chunkById(500, function ($rows) use ($archiveTable, $table, &$count) {
            $ids      = $rows->pluck('id')->all();
            $toInsert = $rows->map(fn($row) => array_merge((array) $row, ['archived_at' => now()]))->all();

            DB::transaction(function () use ($archiveTable, $table, $ids, $toInsert) {
                DB::table($archiveTable)->insertOrIgnore($toInsert);
                DB::table($table)->whereIn('id', $ids)->forceDelete();
            });

            $count += count($ids);
            $this->line("    Archived {$count}/{$count} rows...", null, 'v');
        });

        $this->info("  Archived {$count} records from {$table}");

        return $count;
    }
}
