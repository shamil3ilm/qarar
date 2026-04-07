<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\RecurringJournalTemplate;
use App\Services\Accounting\RecurringJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringJournalController extends Controller
{
    public function __construct(
        private RecurringJournalService $service
    ) {}

    /**
     * List recurring journal templates.
     */
    public function index(Request $request): JsonResponse
    {
        $templates = $this->service->list($request->only([
            'is_active', 'frequency', 'search', 'per_page',
        ]));

        return $this->paginated($templates);
    }

    /**
     * Create a recurring journal template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'frequency' => ['required', Rule::in([
                RecurringJournalTemplate::FREQUENCY_DAILY,
                RecurringJournalTemplate::FREQUENCY_WEEKLY,
                RecurringJournalTemplate::FREQUENCY_MONTHLY,
                RecurringJournalTemplate::FREQUENCY_QUARTERLY,
                RecurringJournalTemplate::FREQUENCY_ANNUALLY,
            ])],
            'interval' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'next_run_date' => ['nullable', 'date'],
            'max_runs' => ['nullable', 'integer', 'min:1'],
            'debit_account_id' => [
                'required',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'credit_account_id' => [
                'required',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'amount' => ['required', 'numeric', 'min:0.0001'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'narration' => ['nullable', 'string'],
            'cost_center_id' => [
                'nullable',
                Rule::exists('cost_centers', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'profit_center_id' => [
                'nullable',
                Rule::exists('profit_centers', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;
        $validated['created_by'] = auth()->id();

        try {
            $template = $this->service->create($validated);
            return $this->created($template, 'Recurring journal template created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a single recurring journal template.
     */
    public function show(RecurringJournalTemplate $recurringJournalTemplate): JsonResponse
    {
        $recurringJournalTemplate->load(['debitAccount', 'creditAccount', 'costCenter', 'profitCenter', 'createdBy']);

        return $this->success($recurringJournalTemplate);
    }

    /**
     * Update a recurring journal template.
     */
    public function update(Request $request, RecurringJournalTemplate $recurringJournalTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'frequency' => ['sometimes', Rule::in([
                RecurringJournalTemplate::FREQUENCY_DAILY,
                RecurringJournalTemplate::FREQUENCY_WEEKLY,
                RecurringJournalTemplate::FREQUENCY_MONTHLY,
                RecurringJournalTemplate::FREQUENCY_QUARTERLY,
                RecurringJournalTemplate::FREQUENCY_ANNUALLY,
            ])],
            'interval' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'next_run_date' => ['nullable', 'date'],
            'max_runs' => ['nullable', 'integer', 'min:1'],
            'debit_account_id' => [
                'sometimes',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'credit_account_id' => [
                'sometimes',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'amount' => ['sometimes', 'numeric', 'min:0.0001'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'narration' => ['nullable', 'string'],
            'cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')
                ->where('organization_id', auth()->user()->organization_id)],
            'profit_center_id' => ['nullable', Rule::exists('profit_centers', 'id')
                ->where('organization_id', auth()->user()->organization_id)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return $this->tryAction(
            fn() => $this->service->update($recurringJournalTemplate, $validated),
            'Recurring journal template updated successfully.'
        );
    }

    /**
     * Delete a recurring journal template.
     */
    public function destroy(RecurringJournalTemplate $recurringJournalTemplate): JsonResponse
    {
        $this->service->delete($recurringJournalTemplate);

        return $this->success(null, 'Recurring journal template deleted successfully.');
    }

    /**
     * Manually trigger one run for a specific template.
     */
    public function execute(RecurringJournalTemplate $recurringJournalTemplate): JsonResponse
    {
        try {
            $entry = $this->service->execute($recurringJournalTemplate);
            return $this->success([
                'journal_entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'status' => $entry->status,
                'template' => $recurringJournalTemplate->fresh(),
            ], 'Recurring journal template executed successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'EXECUTION_FAILED', 422);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 'EXECUTION_ERROR', 500);
        }
    }

    /**
     * Run all active, due templates for the organization.
     */
    public function runDue(): JsonResponse
    {
        $results = $this->service->runDue();

        return $this->success($results, "Processed {$results['processed']} template(s).");
    }
}
