<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\SpecialLedger;
use App\Services\Accounting\SpecialLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpecialLedgerController extends Controller
{
    public function __construct(
        private readonly SpecialLedgerService $service
    ) {}

    /**
     * List all special ledgers for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SpecialLedger::query()->orderBy('code')
            ->when($request->boolean('active_only'), fn($q) => $q->active());

        $ledgers = $query->get();

        return $this->success($ledgers);
    }

    /**
     * Create a new special ledger.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'                 => [
                'required',
                'string',
                'max:50',
                Rule::unique('special_ledgers')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'accounting_principle' => ['required', Rule::in(['ifrs', 'gaap', 'local'])],
            'is_leading'           => ['boolean'],
            'is_active'            => ['boolean'],
            'currency_code'        => ['nullable', 'string', 'size:3'],
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        $ledger = $this->service->createLedger($validated);

        return $this->created($ledger);
    }

    /**
     * Show a single special ledger.
     */
    public function show(int $id): JsonResponse
    {
        $ledger = SpecialLedger::with('mappingRules')->findOrFail($id);

        return $this->success($ledger);
    }

    /**
     * Update a special ledger.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $ledger = SpecialLedger::findOrFail($id);

        $validated = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'accounting_principle' => ['sometimes', Rule::in(['ifrs', 'gaap', 'local'])],
            'is_leading'           => ['boolean'],
            'is_active'            => ['boolean'],
            'currency_code'        => ['nullable', 'string', 'size:3'],
        ]);

        $ledger->update($validated);

        return $this->success($ledger, 'Special ledger updated successfully.');
    }

    /**
     * Delete a special ledger.
     */
    public function destroy(int $id): JsonResponse
    {
        $ledger = SpecialLedger::findOrFail($id);

        if ($ledger->is_leading) {
            return $this->error('Cannot delete the leading ledger.', 'LEADING_LEDGER', 422);
        }

        $ledger->delete();

        return $this->success(null, 'Special ledger deleted successfully.');
    }

    /**
     * Get trial balance for a ledger.
     */
    public function trialBalance(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year' => ['required', 'integer'],
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $rows = $this->service->getTrialBalance(
            $id,
            (int) $validated['fiscal_year'],
            (int) $validated['period']
        );

        return $this->success($rows);
    }

    /**
     * List paginated entries for a ledger.
     */
    public function entries(Request $request, int $id): JsonResponse
    {
        $ledger = SpecialLedger::findOrFail($id);

        $entries = $ledger->entries()
            ->with('account:id,code,name')
            ->when($request->input('fiscal_year'), fn ($q, $y) => $q->where('fiscal_year', (int) $y))
            ->when($request->input('period'), fn ($q, $p) => $q->where('period', (int) $p))
            ->orderByDesc('posting_date')
            ->paginate($request->integer('per_page', 25));

        return $this->paginated($entries);
    }
}
