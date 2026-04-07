<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankStatementImport;
use App\Services\Accounting\BankReconciliationService;
use App\Services\Accounting\EbsParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BankReconciliationController extends Controller
{
    public function __construct(
        private BankReconciliationService $reconciliationService,
        private EbsParserService $ebsParser,
    ) {}

    /**
     * List bank reconciliations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BankReconciliation::with(['bankAccount:id,account_name,bank_name', 'createdBy:id,name'])
            ->orderByDesc('statement_date')
            ->orderByDesc('id')
            ->when($request->has('bank_account_id'), fn($q) => $q->where('bank_account_id', $request->bank_account_id))
            ->when($request->has('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->has('start_date'), fn($q) => $q->whereDate('statement_date', '>=', $request->start_date))
            ->when($request->has('end_date'), fn($q) => $q->whereDate('statement_date', '<=', $request->end_date));

        $reconciliations = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($reconciliations);
    }

    /**
     * Create a new bank reconciliation session.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $reconciliation = $this->reconciliationService->create([
                ...$validated,
                'organization_id' => $this->organizationId($request),
            ], auth()->id());

            return $this->created($reconciliation, 'Bank reconciliation created successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a single bank reconciliation with items.
     */
    public function show(BankReconciliation $bankReconciliation): JsonResponse
    {
        $bankReconciliation->load([
            'bankAccount:id,account_name,bank_name,account_number',
            'items.bankTransaction',
            'createdBy:id,name',
            'completedBy:id,name',
        ]);

        return $this->success($bankReconciliation);
    }

    /**
     * Update a bank reconciliation (only in-progress).
     */
    public function update(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        if ($bankReconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            return $this->error('Only in-progress reconciliations can be updated', 'INVALID_STATUS', 400);
        }

        $validated = $request->validate([
            'statement_balance' => ['sometimes', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $bankReconciliation->update($validated);

        if (isset($validated['statement_balance'])) {
            $bankReconciliation->calculateDifference();
        }

        return $this->success(
            $bankReconciliation->fresh(['bankAccount', 'items']),
            'Bank reconciliation updated successfully'
        );
    }

    /**
     * Auto-match bank transactions.
     */
    public function autoMatch(BankReconciliation $bankReconciliation): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->reconciliationService->autoMatch($bankReconciliation, auth()->id()),
            'Auto-matching completed',
            'AUTO_MATCH_FAILED',
        );
    }

    /**
     * Manually match a bank transaction.
     */
    public function manualMatch(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        $validated = $request->validate([
            'bank_transaction_id' => ['required', 'exists:bank_transactions,id'],
            'matched_transaction_id' => ['nullable', 'integer'],
            'matched_transaction_type' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $item = $this->reconciliationService->manualMatch(
                $bankReconciliation,
                (int) $validated['bank_transaction_id'],
                auth()->id(),
                $validated['matched_transaction_id'] ?? null,
                $validated['matched_transaction_type'] ?? null
            );

            return $this->created($item, 'Transaction matched successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'MATCH_FAILED', 400);
        }
    }

    /**
     * Unmatch a previously matched transaction.
     */
    public function unmatch(BankReconciliation $bankReconciliation, int $itemId): JsonResponse
    {
        return $this->tryAction(
            function () use ($bankReconciliation, $itemId) { $this->reconciliationService->unmatch($bankReconciliation, $itemId); },
            'Transaction unmatched successfully',
            'UNMATCH_FAILED',
        );
    }

    /**
     * Complete a bank reconciliation.
     */
    public function complete(BankReconciliation $bankReconciliation): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->reconciliationService->complete($bankReconciliation, auth()->id()),
            'Bank reconciliation completed successfully',
            'COMPLETE_FAILED',
            400,
        );
    }

    /**
     * Import a bank statement.
     */
    public function importStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'file' => ['required', 'file'],
            'file_type' => ['required', 'string', 'in:csv,ofx,qfx,mt940,camt053'],
            'statement_start_date' => ['nullable', 'date'],
            'statement_end_date' => ['nullable', 'date'],
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store('bank-statements', 'private');

            $import = $this->reconciliationService->importStatement([
                'organization_id' => $this->organizationId($request),
                'bank_account_id' => $validated['bank_account_id'],
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $validated['file_type'],
                'statement_start_date' => $validated['statement_start_date'] ?? null,
                'statement_end_date' => $validated['statement_end_date'] ?? null,
            ]);

            return $this->created($import, 'Bank statement import initiated successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'IMPORT_FAILED', 400);
        }
    }

    /**
     * Parse a bank statement import using the EBS parser (MT940 or CAMT.053).
     * Creates BankTransaction records from the parsed statement.
     */
    public function parseStatement(Request $request, int $importId): JsonResponse
    {
        $import = BankStatementImport::where('organization_id', $this->organizationId($request))
            ->findOrFail($importId);

        if (!in_array($import->file_type, ['mt940', 'camt053'], true)) {
            return $this->error(
                "Format '{$import->file_type}' is not supported by the EBS parser. Supported: mt940, camt053.",
                'UNSUPPORTED_FORMAT',
                422
            );
        }

        if ($import->status === BankStatementImport::STATUS_COMPLETED) {
            return $this->error('Statement has already been parsed.', 'ALREADY_PARSED', 409);
        }

        $content = Storage::disk('private')->get($import->file_path);
        if ($content === null) {
            return $this->error('Statement file not found in storage.', 'FILE_NOT_FOUND', 404);
        }

        try {
            $import->update(['status' => BankStatementImport::STATUS_PROCESSING]);
            $count = $this->ebsParser->import($import, $content);

            return $this->success(
                ['transactions_imported' => $count],
                "Successfully parsed {$count} transaction(s) from the bank statement."
            );
        } catch (\RuntimeException $e) {
            $import->update(['status' => BankStatementImport::STATUS_FAILED, 'errors' => [$e->getMessage()]]);
            return $this->error($e->getMessage(), 'PARSE_FAILED', 422);
        }
    }
}
