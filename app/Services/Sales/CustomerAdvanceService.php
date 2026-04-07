<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Models\Sales\AdvancePayment;
use App\Models\Sales\AdvancePaymentApplication;
use App\Models\Sales\Invoice;
use App\Services\Accounting\JournalService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerAdvanceService
{
    public function __construct(
        private JournalService $journalService,
    ) {}

    /**
     * List advance payments for an organization with optional filters.
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = AdvancePayment::with(['contact:id,contact_name,company_name', 'applications'])
            ->where('organization_id', $filters['organization_id'])
            ->orderByDesc('advance_date');

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('advance_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('advance_date', '<=', $filters['to_date']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Record a new customer advance payment and create the opening journal entry.
     * Dr: Bank / Cash Account
     * Cr: Customer Advance Liability Account (2100 or similar)
     */
    public function store(array $data): AdvancePayment
    {
        return DB::transaction(function () use ($data): AdvancePayment {
            $orgId = (int) $data['organization_id'];

            // Auto-generate advance number when not supplied
            if (empty($data['advance_number'])) {
                $count = AdvancePayment::where('organization_id', $orgId)->withTrashed()->count() + 1;
                $data['advance_number'] = 'ADV-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
            }

            $amount = (float) $data['amount'];

            $advance = AdvancePayment::create(array_merge($data, [
                'applied_amount' => 0,
                'balance_amount' => $amount,
                'status'         => AdvancePayment::STATUS_RECEIVED,
            ]));

            // Create journal entry if bank/cash GL account is resolvable
            if (! empty($data['bank_account_id'])) {
                $je = $this->journalService->create([
                    'organization_id' => $orgId,
                    'reference'       => $advance->advance_number,
                    'description'     => "Customer advance received — {$advance->advance_number}",
                    'entry_date'      => $advance->advance_date->toDateString(),
                    'currency_code'   => $advance->currency_code,
                    'created_by'      => $data['created_by'],
                ], [
                    // Debit: Bank — resolved by linked bank account's GL account
                    [
                        'account_code' => '1020', // Bank / Cash (default)
                        'debit'        => $amount,
                        'credit'       => 0,
                        'description'  => "Advance received from customer",
                    ],
                    // Credit: Customer Advance Liability
                    [
                        'account_code' => '2100', // Customer Deposits / Advances Received
                        'debit'        => 0,
                        'credit'       => $amount,
                        'description'  => "Customer advance liability — {$advance->advance_number}",
                    ],
                ]);

                $advance->update(['journal_entry_id' => $je->id]);
            }

            return $advance->fresh(['contact', 'applications']);
        });
    }

    /**
     * Apply part or all of an advance to an invoice.
     */
    public function applyToInvoice(
        AdvancePayment $advance,
        Invoice $invoice,
        float $amount,
        ?string $notes = null,
    ): AdvancePaymentApplication {
        return DB::transaction(function () use ($advance, $invoice, $amount, $notes): AdvancePaymentApplication {
            // Validate advance is open
            if (! in_array($advance->status, [
                AdvancePayment::STATUS_RECEIVED,
                AdvancePayment::STATUS_PARTIALLY_APPLIED,
            ], true)) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'advance_status' => $advance->status,
                    'message'        => 'Advance is not open for application.',
                ]);
            }

            $balance = (float) $advance->balance_amount;
            $balanceDue = (float) $invoice->amount_due;

            if ($amount <= 0) {
                throw ApiException::fromError(ErrorCodes::VALIDATION_FAILED, [
                    'message' => 'Application amount must be greater than zero.',
                ]);
            }

            if ($amount > $balance) {
                throw ApiException::fromError(ErrorCodes::VALIDATION_FAILED, [
                    'message'          => 'Application amount exceeds advance balance.',
                    'advance_balance'  => $balance,
                    'requested_amount' => $amount,
                ]);
            }

            if ($amount > $balanceDue) {
                throw ApiException::fromError(ErrorCodes::VALIDATION_FAILED, [
                    'message'          => 'Application amount exceeds invoice balance due.',
                    'invoice_balance'  => $balanceDue,
                    'requested_amount' => $amount,
                ]);
            }

            $application = AdvancePaymentApplication::create([
                'advance_payment_id' => $advance->id,
                'invoice_id'         => $invoice->id,
                'applied_amount'     => $amount,
                'applied_date'       => now()->toDateString(),
                'notes'              => $notes,
                'created_by'         => $advance->created_by,
            ]);

            // Update advance balances
            $newApplied  = (float) bcadd((string) $advance->applied_amount, (string) $amount, 4);
            $newBalance  = (float) bcsub((string) $advance->amount, (string) $newApplied, 4);

            $newStatus = match (true) {
                $newBalance <= 0 => AdvancePayment::STATUS_FULLY_APPLIED,
                default          => AdvancePayment::STATUS_PARTIALLY_APPLIED,
            };

            $advance->update([
                'applied_amount' => $newApplied,
                'balance_amount' => max(0, $newBalance),
                'status'         => $newStatus,
            ]);

            // Update invoice payment tracking
            $newAmountPaid = (float) bcadd((string) $invoice->amount_paid, (string) $amount, 4);
            $newAmountDue  = (float) bcsub((string) $invoice->total, (string) $newAmountPaid, 4);

            $invoiceStatus = match (true) {
                $newAmountDue <= 0      => Invoice::STATUS_PAID,
                $newAmountPaid > 0      => Invoice::STATUS_PARTIAL,
                default                 => $invoice->status,
            };

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'amount_due'  => max(0, $newAmountDue),
                'status'      => $invoiceStatus,
            ]);

            // Create clearing journal entry
            $this->journalService->create([
                'organization_id' => $advance->organization_id,
                'reference'       => $advance->advance_number,
                'description'     => "Advance clearing — {$advance->advance_number} against {$invoice->invoice_number}",
                'entry_date'      => now()->toDateString(),
                'currency_code'   => $advance->currency_code,
                'created_by'      => $advance->created_by,
            ], [
                [
                    'account_code' => '2100', // Debit: Customer Advance Liability (clear the liability)
                    'debit'        => $amount,
                    'credit'       => 0,
                    'description'  => "Clear advance liability — {$advance->advance_number}",
                ],
                [
                    'account_code' => '1200', // Credit: Accounts Receivable (reduce outstanding AR)
                    'debit'        => 0,
                    'credit'       => $amount,
                    'description'  => "Applied to invoice {$invoice->invoice_number}",
                ],
            ]);

            return $application->fresh();
        });
    }

    /**
     * Return all open advances for a contact (with remaining balance).
     */
    public function getOpenAdvancesForContact(int $contactId, int $orgId): Collection
    {
        return AdvancePayment::open()
            ->where('organization_id', $orgId)
            ->where('contact_id', $contactId)
            ->where('balance_amount', '>', 0)
            ->with('applications')
            ->orderByDesc('advance_date')
            ->get();
    }

    /**
     * Refund the remaining balance of an advance.
     */
    public function refund(AdvancePayment $advance): void
    {
        DB::transaction(function () use ($advance): void {
            if (! in_array($advance->status, [
                AdvancePayment::STATUS_RECEIVED,
                AdvancePayment::STATUS_PARTIALLY_APPLIED,
            ], true)) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'advance_status' => $advance->status,
                    'message'        => 'Only open advances can be refunded.',
                ]);
            }

            $refundAmount = (float) $advance->balance_amount;

            if ($refundAmount <= 0) {
                throw ApiException::fromError(ErrorCodes::VALIDATION_FAILED, [
                    'message' => 'No balance remaining to refund.',
                ]);
            }

            $advance->update([
                'balance_amount' => 0,
                'status'         => AdvancePayment::STATUS_REFUNDED,
            ]);

            // Journal entry: Dr Customer Advance Liability / Cr Bank
            $this->journalService->create([
                'organization_id' => $advance->organization_id,
                'reference'       => $advance->advance_number,
                'description'     => "Advance refund — {$advance->advance_number}",
                'entry_date'      => now()->toDateString(),
                'currency_code'   => $advance->currency_code,
                'created_by'      => $advance->created_by,
            ], [
                [
                    'account_code' => '2100',
                    'debit'        => $refundAmount,
                    'credit'       => 0,
                    'description'  => "Refund of customer advance — {$advance->advance_number}",
                ],
                [
                    'account_code' => '1020',
                    'debit'        => 0,
                    'credit'       => $refundAmount,
                    'description'  => "Cash/bank payment for advance refund",
                ],
            ]);
        });
    }
}
