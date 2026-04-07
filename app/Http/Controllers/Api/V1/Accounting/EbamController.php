<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankAccountRequest;
use App\Models\Accounting\BankSignatory;
use App\Services\Accounting\EbamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EbamController extends Controller
{
    public function __construct(
        private EbamService $ebamService
    ) {}

    // =========================================================================
    // Signatories
    // =========================================================================

    /**
     * List signatories for a bank account.
     */
    public function signatories(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $query = $bankAccount->signatories()
            ->with('user:id,name,email')
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy('name');

        return $this->paginated($query->paginate($request->integer('per_page', 50)));
    }

    /**
     * Add a signatory to a bank account.
     */
    public function addSignatory(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'title'           => ['nullable', 'string', 'max:100'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'user_id'         => ['nullable', 'exists:users,id'],
            'authority_level' => ['nullable', 'in:single,joint_any,joint_all'],
            'signing_limit'   => ['nullable', 'numeric', 'min:0'],
            'valid_from'      => ['required', 'date'],
            'valid_to'        => ['nullable', 'date', 'after:valid_from'],
        ]);

        $signatory = $this->ebamService->addSignatory($bankAccount, $validated, auth()->id());

        return $this->created($signatory, 'Signatory added successfully.');
    }

    /**
     * Revoke a signatory (expire immediately).
     */
    public function revokeSignatory(Request $request, BankAccount $bankAccount, BankSignatory $bankSignatory): JsonResponse
    {
        if ($bankSignatory->bank_account_id !== $bankAccount->id) {
            return $this->notFound('Signatory not found on this bank account.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $signatory = $this->ebamService->revokeSignatory($bankSignatory, $validated['reason'], auth()->id());

        return $this->success($signatory, 'Signatory revoked successfully.');
    }

    // =========================================================================
    // Bank Account Requests (opening / closing / modification workflow)
    // =========================================================================

    /**
     * List bank account requests.
     */
    public function requests(Request $request): JsonResponse
    {
        $query = BankAccountRequest::with([
            'bankAccount:id,bank_name,account_name,iban',
            'requestedBy:id,name',
            'approvedBy:id,name',
        ])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->request_type, fn ($q, $t) => $q->where('request_type', $t))
            ->orderByDesc('created_at');

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    /**
     * Show a single request.
     */
    public function showRequest(BankAccountRequest $bankAccountRequest): JsonResponse
    {
        $bankAccountRequest->load([
            'bankAccount',
            'requestedBy:id,name',
            'approvedBy:id,name',
            'rejectedBy:id,name',
        ]);

        return $this->success($bankAccountRequest);
    }

    /**
     * Raise a new bank account request (open / close / modify / signatory changes).
     */
    public function createRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_type'    => ['required', 'in:open,close,modify,add_signatory,remove_signatory'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'bank_name'       => ['nullable', 'string', 'max:255'],
            'account_name'    => ['nullable', 'string', 'max:255'],
            'account_type'    => ['nullable', 'in:current,savings,credit_card,cash'],
            'currency_code'   => ['nullable', 'string', 'size:3'],
            'iban'            => ['nullable', 'string', 'max:34'],
            'swift_code'      => ['nullable', 'string', 'max:11'],
            'branch_name'     => ['nullable', 'string', 'max:255'],
            'request_data'    => ['nullable', 'array'],
            'justification'   => ['nullable', 'string'],
        ]);

        // 'open' requests don't need a bank_account_id; others do
        if ($validated['request_type'] !== 'open' && empty($validated['bank_account_id'])) {
            return $this->error('bank_account_id is required for non-open requests.', 'VALIDATION_ERROR', 422);
        }

        $req = $this->ebamService->createRequest(
            $this->organizationId($request),
            $validated,
            auth()->id()
        );

        return $this->created($req, 'Request submitted successfully.');
    }

    /**
     * Approve or reject a pending bank account request.
     * POST /bank-account-requests/{id}/review  {"action": "approve"|"reject", "reason": "..."}
     */
    public function reviewRequest(Request $request, BankAccountRequest $bankAccountRequest): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'notes'  => ['nullable', 'string', 'max:500'],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:500'],
        ]);

        try {
            if ($validated['action'] === 'approve') {
                $req = $this->ebamService->approveRequest(
                    $bankAccountRequest,
                    $validated['notes'] ?? '',
                    auth()->id()
                );
                return $this->success($req, 'Request approved.');
            }

            $req = $this->ebamService->rejectRequest(
                $bankAccountRequest,
                $validated['reason'],
                auth()->id()
            );
            return $this->success($req, 'Request rejected.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACTION_FAILED', 400);
        }
    }

    /**
     * Execute an approved request (creates / closes / modifies the bank account).
     */
    public function executeRequest(BankAccountRequest $bankAccountRequest): JsonResponse
    {
        try {
            $req = $this->ebamService->executeRequest($bankAccountRequest, auth()->id());

            return $this->success($req, 'Request executed successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'EXECUTION_FAILED', 400);
        }
    }
}
