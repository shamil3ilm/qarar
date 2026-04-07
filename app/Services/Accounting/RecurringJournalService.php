<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\RecurringJournalTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecurringJournalService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * List recurring journal templates with optional filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = RecurringJournalTemplate::with(['debitAccount', 'creditAccount', 'createdBy'])
            ->orderBy('name');

        if (!empty($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['frequency'])) {
            $query->where('frequency', $filters['frequency']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 20;

        return $query->paginate($perPage);
    }

    /**
     * Create a new recurring journal template.
     */
    public function create(array $data): RecurringJournalTemplate
    {
        $this->validateFrequency($data['frequency'] ?? '');

        $data['next_run_date'] = $data['next_run_date'] ?? $data['start_date'];
        $data['created_by'] = $data['created_by'] ?? auth()->id();

        return RecurringJournalTemplate::create($data);
    }

    /**
     * Update an existing recurring journal template.
     */
    public function update(RecurringJournalTemplate $template, array $data): RecurringJournalTemplate
    {
        if (isset($data['frequency'])) {
            $this->validateFrequency($data['frequency']);
        }

        $template->update($data);

        return $template->fresh(['debitAccount', 'creditAccount']);
    }

    /**
     * Delete (soft-delete) a recurring journal template.
     */
    public function delete(RecurringJournalTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Execute a single recurring journal template run.
     * Creates and posts a journal entry, then advances the schedule.
     */
    public function execute(RecurringJournalTemplate $template): JournalEntry
    {
        if (!$template->is_active) {
            throw new InvalidArgumentException("Template '{$template->name}' is not active.");
        }

        return DB::transaction(function () use ($template): JournalEntry {
            $runDate = now()->toDateString();

            $entry = $this->journalService->createEntry([
                'organization_id' => $template->organization_id,
                'entry_date' => $runDate,
                'reference' => "RJT-{$template->id}",
                'description' => $template->narration ?? $template->name,
                'currency_code' => $template->currency_code,
                'source_type' => RecurringJournalTemplate::class,
                'source_id' => $template->id,
            ], [
                [
                    'account_id' => $template->debit_account_id,
                    'debit' => (float) $template->amount,
                    'credit' => 0,
                    'description' => $template->narration ?? $template->name,
                    'cost_center_id' => $template->cost_center_id,
                ],
                [
                    'account_id' => $template->credit_account_id,
                    'debit' => 0,
                    'credit' => (float) $template->amount,
                    'description' => $template->narration ?? $template->name,
                    'cost_center_id' => $template->cost_center_id,
                ],
            ]);

            $this->journalService->postEntry($entry);

            $newRunCount = $template->run_count + 1;
            $nextRunDate = $template->calculateNextRunDate(now());
            $shouldDeactivate = false;

            if ($template->max_runs !== null && $newRunCount >= $template->max_runs) {
                $shouldDeactivate = true;
            }

            if ($template->end_date !== null && $nextRunDate->gt($template->end_date)) {
                $shouldDeactivate = true;
            }

            $template->update([
                'last_run_date' => $runDate,
                'run_count' => $newRunCount,
                'next_run_date' => $nextRunDate->toDateString(),
                'is_active' => !$shouldDeactivate,
            ]);

            return $entry;
        });
    }

    /**
     * Run all active, due templates for the authenticated organization.
     * Returns an array summarising results.
     */
    public function runDue(): array
    {
        $templates = RecurringJournalTemplate::active()->get();

        $results = [
            'processed' => 0,
            'failed' => 0,
            'entries' => [],
            'errors' => [],
        ];

        foreach ($templates as $template) {
            try {
                $entry = $this->execute($template);
                $results['processed']++;
                $results['entries'][] = [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'journal_entry_id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                ];
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function validateFrequency(string $frequency): void
    {
        $valid = [
            RecurringJournalTemplate::FREQUENCY_DAILY,
            RecurringJournalTemplate::FREQUENCY_WEEKLY,
            RecurringJournalTemplate::FREQUENCY_MONTHLY,
            RecurringJournalTemplate::FREQUENCY_QUARTERLY,
            RecurringJournalTemplate::FREQUENCY_ANNUALLY,
        ];

        if (!in_array($frequency, $valid, true)) {
            throw new InvalidArgumentException("Invalid frequency '{$frequency}'.");
        }
    }
}
