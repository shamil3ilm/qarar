<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\SpecialLedger;
use App\Services\Accounting\ParallelLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parallel Ledger Controller — SAP FI parallel accounting.
 *
 * GET  /accounting/parallel-ledgers                              list ledgers
 * POST /accounting/parallel-ledgers                             create ledger
 * GET  /accounting/parallel-ledgers/{id}/comparison             leading vs parallel balances
 * POST /accounting/parallel-ledgers/{id}/post/{journalEntryId}  manually post to ledger
 */
class ParallelLedgerController extends Controller
{
    public function __construct(
        private readonly ParallelLedgerService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $ledgers = $this->service->getLedgers($request->user()->organization_id);

        return $this->successResponse($ledgers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'                 => 'required|string|max:10',
            'name'                 => 'required|string|max:100',
            'description'          => 'nullable|string|max:500',
            'accounting_principle' => 'required|string|in:IFRS,LOCAL,TAX,MGMT',
            'is_leading'           => 'boolean',
            'currency_code'        => 'required|string|size:3',
        ]);

        $ledger = SpecialLedger::create([
            'organization_id'      => $request->user()->organization_id,
            ...$validated,
            'is_active' => true,
        ]);

        return $this->successResponse($ledger, 'Parallel ledger created', 201);
    }

    public function comparison(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'fiscal_year' => 'required|string|size:4',
            'period'      => 'nullable|string|size:2',
        ]);

        $result = $this->service->getParallelComparison(
            $request->user()->organization_id,
            (int) $id,
            $request->fiscal_year,
            $request->period,
        );

        return $this->successResponse($result);
    }

    public function postEntry(Request $request, string $id, string $journalEntryId): JsonResponse
    {
        $ledger       = SpecialLedger::findOrFail($id);
        $journalEntry = JournalEntry::with('lines')->findOrFail($journalEntryId);

        $this->service->postToLedger($journalEntry, $ledger);

        return $this->successResponse(null, 'Journal entry posted to parallel ledger');
    }
}
