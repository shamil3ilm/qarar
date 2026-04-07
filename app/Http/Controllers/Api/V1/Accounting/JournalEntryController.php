<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Concerns\SupportsAgGrid;
use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JournalEntryController extends Controller
{
    use SupportsAgGrid;
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * List journal entries.
     */
    public function index(Request $request): JsonResponse
    {
        $query = JournalEntry::with(['branch:id,name', 'createdBy:id,name'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->when($request->has('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->has('fiscal_year_id'), fn($q) => $q->where('fiscal_year_id', $request->fiscal_year_id))
            ->when($request->has('start_date'), fn($q) => $q->whereDate('entry_date', '>=', $request->start_date))
            ->when($request->has('end_date'), fn($q) => $q->whereDate('entry_date', '<=', $request->end_date))
            ->when($request->has('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q) use ($search) {
                    $q->where('entry_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        if ($this->isAgGridRequest($request)) {
            return $this->applyAgGrid($query, $request);
        }

        $entries = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($entries);
    }

    /**
     * Show single journal entry with lines.
     */
    public function show(JournalEntry $journalEntry): JsonResponse
    {
        $journalEntry->load([
            'lines.account:id,code,name',
            'branch:id,name',
            'fiscalYear:id,name',
            'createdBy:id,name',
            'postedBy:id,name',
        ]);

        return $this->success($journalEntry);
    }

    /**
     * Create new journal entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'currency_code' => ['required', 'exists:currencies,code'],
            'exchange_rate' => ['sometimes', 'numeric', 'min:0.000001'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.debit' => ['required_without:lines.*.credit', 'numeric', 'min:0'],
            'lines.*.credit' => ['required_without:lines.*.debit', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('organization_id', auth()->user()->organization_id)],
        ]);

        try {
            $entry = $this->journalService->createEntry([
                'organization_id' => auth()->user()->organization_id,
                'branch_id' => $validated['branch_id'] ?? null,
                'entry_date' => $validated['entry_date'],
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'currency_code' => $validated['currency_code'],
                'exchange_rate' => $validated['exchange_rate'] ?? 1,
            ], $validated['lines']);

            return $this->created($entry, 'Journal entry created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Update draft journal entry.
     */
    public function update(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        if ($journalEntry->status !== JournalEntry::STATUS_DRAFT) {
            return $this->error('Only draft entries can be updated', 'INVALID_STATUS', 400);
        }

        $validated = $request->validate([
            'entry_date' => ['sometimes', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'currency_code' => ['sometimes', 'exists:currencies,code'],
            'exchange_rate' => ['sometimes', 'numeric', 'min:0.000001'],
            'lines' => ['sometimes', 'array', 'min:2'],
            'lines.*.account_id' => ['required_with:lines', Rule::exists('chart_of_accounts', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.debit' => ['required_without:lines.*.credit', 'numeric', 'min:0'],
            'lines.*.credit' => ['required_without:lines.*.debit', 'numeric', 'min:0'],
        ]);

        // Update header
        $journalEntry->update(collect($validated)->except('lines')->toArray());

        // Update lines if provided
        if (isset($validated['lines'])) {
            $journalEntry->lines()->delete();

            foreach ($validated['lines'] as $index => $lineData) {
                $journalEntry->lines()->create([
                    'account_id' => $lineData['account_id'],
                    'description' => $lineData['description'] ?? null,
                    'debit' => $lineData['debit'] ?? 0,
                    'credit' => $lineData['credit'] ?? 0,
                    'line_order' => $index,
                ]);
            }

            $journalEntry->recalculateTotals();
        }

        return $this->success($journalEntry->fresh(['lines.account']), 'Journal entry updated successfully');
    }

    /**
     * Delete draft journal entry.
     */
    public function destroy(JournalEntry $journalEntry): JsonResponse
    {
        if ($journalEntry->status !== JournalEntry::STATUS_DRAFT) {
            return $this->error('Only draft entries can be deleted', 'INVALID_STATUS', 400);
        }

        $journalEntry->delete();

        return $this->success(null, 'Journal entry deleted successfully');
    }

    /**
     * Post a draft journal entry.
     */
    public function post(JournalEntry $journalEntry): JsonResponse
    {
        return $this->tryAction(
            function () use ($journalEntry) { $this->journalService->postEntry($journalEntry); return $journalEntry->fresh(); },
            'Journal entry posted successfully',
            'POST_FAILED',
            400,
        );
    }

    /**
     * Void a posted journal entry.
     */
    public function void(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return $this->tryAction(
            function () use ($journalEntry, $validated) { $this->journalService->voidEntry($journalEntry, $validated['reason']); return $journalEntry->fresh(); },
            'Journal entry voided successfully',
            'VOID_FAILED',
            400,
        );
    }

    /**
     * Reverse a posted journal entry.
     */
    public function reverse(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return $this->tryAction(
            fn() => $this->journalService->reverseEntry($journalEntry, $validated['reason']),
            'Journal entry reversed successfully',
            'REVERSAL_FAILED',
            400,
        );
    }
}
