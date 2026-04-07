<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\DocumentLegalHold;
use App\Models\Core\RetentionPolicy;
use App\Models\Core\RetentionScheduleRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DocumentRetentionService
{
    /**
     * Known document-type → table/model mappings.
     * Extend this map as more document types are added.
     */
    private const DOCUMENT_TABLE_MAP = [
        'invoice'       => ['table' => 'invoices',       'date_col' => 'invoice_date'],
        'bill'          => ['table' => 'bills',           'date_col' => 'bill_date'],
        'journal_entry' => ['table' => 'journal_entries', 'date_col' => 'entry_date'],
        'payslip'       => ['table' => 'payslips',        'date_col' => 'pay_date'],
        'purchase_order'=> ['table' => 'purchase_orders', 'date_col' => 'order_date'],
    ];

    /**
     * Eloquent model class for each document type.
     * Used so that archive/delete operations fire model events (HasAuditTrail).
     */
    private const DOCUMENT_MODEL_MAP = [
        'invoice'       => \App\Models\Sales\Invoice::class,
        'bill'          => \App\Models\Purchase\Bill::class,
        'journal_entry' => \App\Models\Accounting\JournalEntry::class,
        'payslip'       => \App\Models\HR\Payslip::class,
        'purchase_order'=> \App\Models\Purchase\PurchaseOrder::class,
    ];

    // ---------------------------------------------------------------
    // Policy management
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function storePolicy(array $data): RetentionPolicy
    {
        return RetentionPolicy::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePolicy(RetentionPolicy $policy, array $data): RetentionPolicy
    {
        $policy->update($data);

        return $policy->fresh();
    }

    // ---------------------------------------------------------------
    // Legal holds
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    public function placeLegalHold(array $data): DocumentLegalHold
    {
        $data['held_by']    = $data['held_by'] ?? auth()->id();
        $data['is_active']  = true;

        return DocumentLegalHold::create($data);
    }

    public function releaseLegalHold(DocumentLegalHold $hold): void
    {
        $hold->update(['is_active' => false]);
    }

    // ---------------------------------------------------------------
    // Schedule runner
    // ---------------------------------------------------------------

    public function runRetentionSchedule(int $orgId): RetentionScheduleRun
    {
        $run = RetentionScheduleRun::create([
            'organization_id'              => $orgId,
            'run_at'                       => now(),
            'documents_evaluated'          => 0,
            'documents_archived'           => 0,
            'documents_deleted'            => 0,
            'documents_skipped_legal_hold' => 0,
        ]);

        $policies = RetentionPolicy::where('organization_id', $orgId)
            ->where('is_active', true)
            ->get();

        $evaluated  = 0;
        $archived   = 0;
        $deleted    = 0;
        $skipped    = 0;
        $logLines   = [];

        foreach ($policies as $policy) {
            $tableInfo = self::DOCUMENT_TABLE_MAP[$policy->document_type] ?? null;

            if ($tableInfo === null) {
                $logLines[] = "Skipped unknown document_type: {$policy->document_type}";
                continue;
            }

            $table   = $tableInfo['table'];
            $dateCol = $tableInfo['date_col'];

            if (!Schema::hasTable($table)) {
                $logLines[] = "Table {$table} does not exist, skipping.";
                continue;
            }

            $cutoffDate = now()->subYears($policy->retention_years)->toDateString();

            $docs = DB::table($table)
                ->where('organization_id', $orgId)
                ->where($dateCol, '<', $cutoffDate)
                ->whereNull('deleted_at')
                ->select(['id'])
                ->get();

            foreach ($docs as $doc) {
                $evaluated++;

                // Check active legal hold
                $hasHold = DocumentLegalHold::where('organization_id', $orgId)
                    ->where('document_type', $policy->document_type)
                    ->where('document_id', $doc->id)
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('hold_until')
                            ->orWhere('hold_until', '>=', now()->toDateString());
                    })
                    ->exists();

                if ($hasHold && $policy->legal_hold_override) {
                    $skipped++;
                    continue;
                }

                try {
                    match ($policy->action_on_expiry) {
                        'archive' => $this->archiveDocument($table, $doc->id, $logLines, $policy->document_type),
                        'delete'  => $this->deleteDocument($table, $doc->id, $logLines, $policy->document_type),
                        default   => $logLines[] = "notify_only: {$table}#{$doc->id} is past retention.",
                    };

                    if ($policy->action_on_expiry === 'archive') {
                        $archived++;
                    } elseif ($policy->action_on_expiry === 'delete') {
                        $deleted++;
                    }
                } catch (\Throwable $e) {
                    $logLines[] = "Error processing {$table}#{$doc->id}: {$e->getMessage()}";
                    Log::error('Retention schedule processing error', [
                        'table'  => $table,
                        'doc_id' => $doc->id,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }
        }

        $run->update([
            'documents_evaluated'          => $evaluated,
            'documents_archived'           => $archived,
            'documents_deleted'            => $deleted,
            'documents_skipped_legal_hold' => $skipped,
            'run_log'                      => implode("\n", $logLines),
        ]);

        return $run->fresh();
    }

    /**
     * Preview documents expiring within the next N days.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getExpiringDocuments(int $orgId, int $withinDays = 90): array
    {
        $policies = RetentionPolicy::where('organization_id', $orgId)
            ->where('is_active', true)
            ->get();

        $expiring = [];

        foreach ($policies as $policy) {
            $tableInfo = self::DOCUMENT_TABLE_MAP[$policy->document_type] ?? null;

            if ($tableInfo === null || !Schema::hasTable($tableInfo['table'])) {
                continue;
            }

            $table      = $tableInfo['table'];
            $dateCol    = $tableInfo['date_col'];
            $cutoffDate = now()->subYears($policy->retention_years)->toDateString();
            $fromDate   = now()->subYears($policy->retention_years)->subDays($withinDays)->toDateString();

            $docs = DB::table($table)
                ->where('organization_id', $orgId)
                ->whereBetween($dateCol, [$fromDate, $cutoffDate])
                ->whereNull('deleted_at')
                ->select(['id', $dateCol . ' as document_date'])
                ->limit(200)
                ->get();

            foreach ($docs as $doc) {
                $expiring[] = [
                    'document_type'  => $policy->document_type,
                    'document_id'    => $doc->id,
                    'document_date'  => $doc->document_date,
                    'expires_on'     => date('Y-m-d', strtotime($doc->document_date . " +{$policy->retention_years} years")),
                    'action'         => $policy->action_on_expiry,
                    'policy_name'    => $policy->policy_name,
                ];
            }
        }

        return $expiring;
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * @param  string[]  &$logLines
     */
    private function archiveDocument(string $table, int $docId, array &$logLines, string $docType = ''): void
    {
        if (!Schema::hasColumn($table, 'is_archived')) {
            $logLines[] = "Archive skipped (no is_archived column): {$table}#{$docId}";
            return;
        }

        // Load through Eloquent so HasAuditTrail fires the updated event.
        $modelClass = self::DOCUMENT_MODEL_MAP[$docType] ?? null;

        if ($modelClass && $model = $modelClass::find($docId)) {
            $model->update(['is_archived' => true]);
        } else {
            DB::table($table)->where('id', $docId)->update(['is_archived' => true]);
        }

        $logLines[] = "Archived: {$table}#{$docId}";
    }

    /**
     * @param  string[]  &$logLines
     */
    private function deleteDocument(string $table, int $docId, array &$logLines, string $docType = ''): void
    {
        // Load through Eloquent so HasAuditTrail fires the deleted/updated event.
        $modelClass = self::DOCUMENT_MODEL_MAP[$docType] ?? null;

        if ($modelClass && $model = $modelClass::find($docId)) {
            // SoftDeletes::delete() sets deleted_at; non-soft models use plain delete().
            $model->delete();
            $logLines[] = "Soft-deleted: {$table}#{$docId}";
        } elseif (Schema::hasColumn($table, 'deleted_at')) {
            DB::table($table)->where('id', $docId)->update(['deleted_at' => now()]);
            $logLines[] = "Soft-deleted (fallback): {$table}#{$docId}";
        } else {
            DB::table($table)->where('id', $docId)->delete();
            $logLines[] = "Hard-deleted: {$table}#{$docId}";
        }
    }
}
