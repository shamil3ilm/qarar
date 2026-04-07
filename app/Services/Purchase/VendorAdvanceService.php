<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Accounting\Account;
use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorAdvanceClearing;
use App\Models\Purchase\VendorAdvancePayment;
use App\Models\Purchase\VendorAdvanceRequest;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorAdvanceService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private JournalService $journalService
    ) {}

    /**
     * Create an advance payment request.
     */
    public function createRequest(array $data): VendorAdvanceRequest
    {
        if (empty($data['request_number'])) {
            $data['request_number'] = $this->numberGenerator->generate('VAR');
        }

        $data['requested_by'] = $data['requested_by'] ?? auth()->id();
        $data['status'] = VendorAdvanceRequest::STATUS_DRAFT;

        return VendorAdvanceRequest::create($data);
    }

    /**
     * Approve an advance payment request.
     */
    public function approveRequest(VendorAdvanceRequest $request): VendorAdvanceRequest
    {
        if (!$request->canBeApproved()) {
            throw new \InvalidArgumentException('Only draft requests can be approved.');
        }

        $request->update([
            'status' => VendorAdvanceRequest::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $request->fresh();
    }

    /**
     * Record an actual payment against an approved advance request.
     */
    public function recordPayment(VendorAdvanceRequest $request, array $paymentData): VendorAdvancePayment
    {
        if (!$request->canBePaid()) {
            throw new \InvalidArgumentException('Advance request must be approved before recording a payment.');
        }

        return DB::transaction(function () use ($request, $paymentData) {
            $paymentData['advance_request_id'] = $request->id;
            $paymentData['payment_date'] = $paymentData['payment_date'] ?? now()->toDateString();

            $payment = VendorAdvancePayment::create($paymentData);

            // Generate GL entry: Debit Vendor Advance / Credit Bank
            try {
                $journalEntryId = $this->createPaymentJournalEntry($request, $payment);
                $payment->update(['journal_entry_id' => $journalEntryId]);
            } catch (\Throwable $e) {
                Log::warning('Vendor advance payment journal entry failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $request->update(['status' => VendorAdvanceRequest::STATUS_PAID]);

            return $payment->fresh(['advanceRequest', 'journalEntry']);
        });
    }

    /**
     * Clear an advance payment against a supplier bill.
     */
    public function clearAgainstBill(VendorAdvancePayment $payment, Bill $bill, float $amount): VendorAdvanceClearing
    {
        if ($payment->isFullyCleared()) {
            throw new \InvalidArgumentException('Advance payment is already fully cleared.');
        }

        // Ensure the advance and the bill belong to the same organization
        $advanceOrgId = $payment->advanceRequest->organization_id ?? $payment->organization_id ?? null;
        if ($advanceOrgId !== null && $advanceOrgId !== $bill->organization_id) {
            throw new \InvalidArgumentException('Advance and bill must belong to the same organization.');
        }

        // Ensure the advance and the bill belong to the same supplier to prevent
        // cross-supplier clearing which would corrupt AP balances.
        $advanceSupplierContactId = $payment->advanceRequest->contact_id ?? $payment->contact_id ?? null;
        if ($advanceSupplierContactId !== null && $bill->contact_id !== $advanceSupplierContactId) {
            throw new \InvalidArgumentException(
                'Cannot clear advance against a bill from a different supplier.'
            );
        }

        return DB::transaction(function () use ($payment, $bill, $amount) {
            // Lock the advance payment row to prevent concurrent over-clearing
            $payment = VendorAdvancePayment::lockForUpdate()->findOrFail($payment->id);

            $uncleared = $payment->getUnclearedAmount();

            if (bccomp((string) $amount, (string) $uncleared, 4) > 0) {
                throw new \InvalidArgumentException(
                    "Clearing amount ({$amount}) exceeds available uncleared balance ({$uncleared})."
                );
            }

            $clearing = VendorAdvanceClearing::create([
                'advance_payment_id' => $payment->id,
                'bill_id' => $bill->id,
                'cleared_amount' => $amount,
                'clearing_date' => now()->toDateString(),
            ]);

            // Generate GL clearing entry: Debit AP / Credit Vendor Advance
            try {
                $journalEntryId = $this->createClearingJournalEntry($payment, $bill, $amount);
                $clearing->update(['journal_entry_id' => $journalEntryId]);
            } catch (\Throwable $e) {
                Log::warning('Vendor advance clearing journal entry failed', [
                    'clearing_id' => $clearing->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // If advance request is fully cleared, update its status
            $advanceRequest = $payment->advanceRequest;

            if ($payment->fresh()->isFullyCleared()) {
                $advanceRequest->update(['status' => VendorAdvanceRequest::STATUS_CLEARED]);
            }

            return $clearing->fresh(['advancePayment', 'bill']);
        });
    }

    private function createPaymentJournalEntry(VendorAdvanceRequest $request, VendorAdvancePayment $payment): ?int
    {
        $orgId = $request->organization_id;
        $amount = (float) $payment->amount;

        // Vendor advance prepayment account (asset) and bank/cash account
        $advanceAccount = Account::where('organization_id', $orgId)
            ->where('account_type', 'asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%Advance%')
                    ->orWhere('name', 'like', '%Prepayment%');
            })
            ->first();

        $bankAccount = $payment->bank_account_id
            ? Account::find($payment->bank_account_id)
            : Account::where('organization_id', $orgId)
                ->where('account_type', 'asset')
                ->where(function ($q) {
                    $q->where('name', 'like', '%Bank%')
                        ->orWhere('name', 'like', '%Cash%');
                })
                ->first();

        if (!$advanceAccount || !$bankAccount) {
            Log::info('Vendor advance payment journal entry skipped: accounts not configured', [
                'request_id' => $request->id,
            ]);

            return null;
        }

        $entry = $this->journalService->createEntry([
            'organization_id' => $orgId,
            'entry_date' => $payment->payment_date->toDateString(),
            'reference' => $request->request_number,
            'description' => "Vendor Advance Payment - {$request->request_number}",
            'status' => 'posted',
        ], [
            [
                'account_id' => $advanceAccount->id,
                'description' => "Vendor advance paid - {$request->request_number}",
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'account_id' => $bankAccount->id,
                'description' => "Bank/cash disbursement - {$request->request_number}",
                'debit' => 0,
                'credit' => $amount,
            ],
        ]);

        return $entry?->id;
    }

    private function createClearingJournalEntry(VendorAdvancePayment $payment, Bill $bill, float $amount): ?int
    {
        $request = $payment->advanceRequest;
        $orgId = $request->organization_id;

        $advanceAccount = Account::where('organization_id', $orgId)
            ->where('account_type', 'asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%Advance%')
                    ->orWhere('name', 'like', '%Prepayment%');
            })
            ->first();

        $apAccount = Account::where('organization_id', $orgId)
            ->where('account_type', 'liability')
            ->where(function ($q) {
                $q->where('name', 'like', '%Accounts Payable%')
                    ->orWhere('name', 'like', '%Creditors%');
            })
            ->first();

        if (!$advanceAccount || !$apAccount) {
            Log::info('Vendor advance clearing journal entry skipped: accounts not configured', [
                'payment_id' => $payment->id,
            ]);

            return null;
        }

        $entry = $this->journalService->createEntry([
            'organization_id' => $orgId,
            'entry_date' => now()->toDateString(),
            'reference' => $bill->bill_number ?? (string) $bill->id,
            'description' => "Vendor Advance Clearing - {$request->request_number}",
            'status' => 'posted',
        ], [
            [
                'account_id' => $apAccount->id,
                'description' => "AP cleared against advance {$request->request_number}",
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'account_id' => $advanceAccount->id,
                'description' => "Advance cleared against bill {$bill->bill_number}",
                'debit' => 0,
                'credit' => $amount,
            ],
        ]);

        return $entry?->id;
    }
}
