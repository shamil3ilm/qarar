<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankAccountRequest;
use App\Models\Accounting\BankSignatory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * eBAM — Electronic Bank Account Management (SAP FI-BL EBAM).
 *
 * Centralizes bank account master data lifecycle:
 * - Signatory management (add / expire / revoke)
 * - Account opening / closing workflow (request → approve → execute)
 * - Account modification requests with approvals
 */
class EbamService
{
    // -------------------------------------------------------------------------
    // Signatory management
    // -------------------------------------------------------------------------

    public function addSignatory(BankAccount $bankAccount, array $data, int $userId): BankSignatory
    {
        return BankSignatory::create([
            'organization_id' => $bankAccount->organization_id,
            'bank_account_id' => $bankAccount->id,
            'user_id'         => $data['user_id'] ?? null,
            'name'            => $data['name'],
            'title'           => $data['title'] ?? null,
            'email'           => $data['email'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'authority_level' => $data['authority_level'] ?? BankSignatory::AUTHORITY_SINGLE,
            'signing_limit'   => $data['signing_limit'] ?? null,
            'valid_from'      => $data['valid_from'],
            'valid_to'        => $data['valid_to'] ?? null,
            'is_active'       => true,
            'created_by'      => $userId,
        ]);
    }

    public function revokeSignatory(BankSignatory $signatory, string $reason, int $userId): BankSignatory
    {
        $signatory->update([
            'is_active' => false,
            'valid_to'  => now()->toDateString(),
        ]);

        return $signatory->fresh();
    }

    // -------------------------------------------------------------------------
    // Bank account request workflow
    // -------------------------------------------------------------------------

    /**
     * Raise a request to open / close / modify a bank account or manage signatories.
     */
    public function createRequest(int $organizationId, array $data, int $userId): BankAccountRequest
    {
        return BankAccountRequest::create([
            'organization_id' => $organizationId,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'request_type'    => $data['request_type'],
            'status'          => BankAccountRequest::STATUS_PENDING,
            'bank_name'       => $data['bank_name'] ?? null,
            'account_name'    => $data['account_name'] ?? null,
            'account_type'    => $data['account_type'] ?? null,
            'currency_code'   => $data['currency_code'] ?? null,
            'iban'            => $data['iban'] ?? null,
            'swift_code'      => $data['swift_code'] ?? null,
            'branch_name'     => $data['branch_name'] ?? null,
            'request_data'    => $data['request_data'] ?? null,
            'justification'   => $data['justification'] ?? null,
            'requested_by'    => $userId,
        ]);
    }

    /**
     * Approve a pending request.
     */
    public function approveRequest(BankAccountRequest $req, string $notes, int $approverId): BankAccountRequest
    {
        if (! $req->canBeApproved()) {
            throw new InvalidArgumentException("Request #{$req->id} is not in pending status.");
        }

        $req->update([
            'status'         => BankAccountRequest::STATUS_APPROVED,
            'approved_by'    => $approverId,
            'approval_notes' => $notes,
            'approved_at'    => now(),
        ]);

        return $req->fresh();
    }

    /**
     * Reject a pending request.
     */
    public function rejectRequest(BankAccountRequest $req, string $reason, int $rejecterId): BankAccountRequest
    {
        if (! $req->canBeApproved()) {
            throw new InvalidArgumentException("Request #{$req->id} is not in pending status.");
        }

        $req->update([
            'status'           => BankAccountRequest::STATUS_REJECTED,
            'rejected_by'      => $rejecterId,
            'rejection_reason' => $reason,
            'rejected_at'      => now(),
        ]);

        return $req->fresh();
    }

    /**
     * Execute an approved request.
     *
     * - open: creates a new BankAccount record
     * - close: marks the bank account inactive
     * - modify: updates the bank account fields provided in request_data
     * - add_signatory / remove_signatory: managed separately via addSignatory/revokeSignatory
     */
    public function executeRequest(BankAccountRequest $req, int $userId): BankAccountRequest
    {
        if (! $req->canBeExecuted()) {
            throw new InvalidArgumentException("Request #{$req->id} must be approved before execution.");
        }

        return DB::transaction(function () use ($req, $userId): BankAccountRequest {
            match ($req->request_type) {
                BankAccountRequest::TYPE_OPEN  => $this->executeOpen($req, $userId),
                BankAccountRequest::TYPE_CLOSE => $this->executeClose($req),
                BankAccountRequest::TYPE_MODIFY => $this->executeModify($req),
                default => null,
            };

            $req->update([
                'status'      => BankAccountRequest::STATUS_EXECUTED,
                'executed_at' => now(),
            ]);

            return $req->fresh(['bankAccount']);
        });
    }

    // -------------------------------------------------------------------------
    // Private execution helpers
    // -------------------------------------------------------------------------

    private function executeOpen(BankAccountRequest $req, int $userId): void
    {
        $extraData = $req->request_data ?? [];

        $account = BankAccount::create([
            'organization_id' => $req->organization_id,
            'bank_name'       => $req->bank_name,
            'account_name'    => $req->account_name,
            'account_number'  => $extraData['account_number'] ?? 'PENDING',
            'account_type'    => $req->account_type ?? BankAccount::TYPE_CURRENT,
            'currency_code'   => $req->currency_code ?? 'SAR',
            'iban'            => $req->iban,
            'swift_code'      => $req->swift_code,
            'branch_name'     => $req->branch_name,
            'gl_account_id'   => $extraData['gl_account_id'] ?? null,
            'is_active'       => true,
        ]);

        $req->bank_account_id = $account->id;
        $req->save();
    }

    private function executeClose(BankAccountRequest $req): void
    {
        if ($req->bankAccount !== null) {
            $req->bankAccount->update(['is_active' => false]);
        }
    }

    private function executeModify(BankAccountRequest $req): void
    {
        $data = $req->request_data ?? [];

        if ($req->bankAccount !== null && ! empty($data)) {
            $allowed = ['bank_name', 'account_name', 'branch_name', 'currency_code', 'is_active'];
            $updates = array_intersect_key($data, array_flip($allowed));

            if (! empty($updates)) {
                $req->bankAccount->update($updates);
            }
        }
    }
}
