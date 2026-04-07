<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\WithholdingTaxCode;
use App\Models\Accounting\WithholdingTaxLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Withholding Tax Service (SAP F.67/F.68 equivalent).
 *
 * Responsibilities:
 *  - Manage WHT master codes (rates, GL accounts)
 *  - Deduct / collect WHT when a payment is posted
 *  - Post the WHT GL entry (Dr Expense / Cr WHT Payable or Dr WHT Receivable / Cr Income)
 *  - Issue WHT certificates
 *  - Reporting: WHT summary per period / contact
 */
class WithholdingTaxService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    // =========================================================================
    // WHT Code management
    // =========================================================================

    public function listCodes(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = WithholdingTaxCode::where('organization_id', $organizationId)
            ->orderBy('code');

        if (!empty($filters['applicable_to'])) {
            $query->where('applicable_to', $filters['applicable_to']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function createCode(array $data, int $organizationId): WithholdingTaxCode
    {
        $this->assertCodeUnique($data['code'], $organizationId);

        return WithholdingTaxCode::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    public function updateCode(WithholdingTaxCode $code, array $data): WithholdingTaxCode
    {
        if (isset($data['code']) && $data['code'] !== $code->code) {
            $this->assertCodeUnique($data['code'], $code->organization_id);
        }

        $code->update($data);

        return $code->fresh();
    }

    public function deleteCode(WithholdingTaxCode $code): void
    {
        if ($code->lines()->exists()) {
            throw new InvalidArgumentException('Cannot delete a WHT code that has been applied to payments.');
        }

        $code->delete();
    }

    // =========================================================================
    // Apply WHT to a payment
    // =========================================================================

    /**
     * Calculate the WHT amount for a payment without persisting anything.
     *
     * @return array{wht_code_id: int, wht_rate: float, wht_amount: float, net_amount: float}
     */
    public function calculate(WithholdingTaxCode $code, float $grossAmount): array
    {
        $whtAmount = $code->compute($grossAmount);

        return [
            'wht_code_id' => $code->id,
            'wht_rate'    => (float) $code->rate,
            'wht_amount'  => $whtAmount,
            'net_amount'  => round($grossAmount - $whtAmount, 4),
        ];
    }

    /**
     * Deduct WHT from a payment and post the GL entry.
     *
     * @param  array  $paymentContext  [payment_type, payment_id, contact_id, gross_amount, currency_code, transaction_date, organization_id, branch_id?]
     * @param  array  $journalMeta    forwarded to JournalService::createEntry()
     */
    public function applyToPayment(
        WithholdingTaxCode $code,
        array $paymentContext,
        array $journalMeta
    ): WithholdingTaxLine {
        $gross = (float) $paymentContext['gross_amount'];
        if ($gross <= 0) {
            throw new InvalidArgumentException('Gross amount must be positive.');
        }

        $calc = $this->calculate($code, $gross);

        return DB::transaction(function () use ($code, $paymentContext, $journalMeta, $calc, $gross): WithholdingTaxLine {
            $organizationId = (int) $paymentContext['organization_id'];

            $je = $this->postWhtJournal($code, $paymentContext, $calc, $journalMeta, $organizationId);

            return WithholdingTaxLine::create([
                'organization_id'    => $organizationId,
                'wht_code_id'        => $code->id,
                'payment_type'       => $paymentContext['payment_type'],
                'payment_id'         => $paymentContext['payment_id'],
                'contact_id'         => $paymentContext['contact_id'] ?? null,
                'gross_amount'       => $gross,
                'wht_rate'           => $calc['wht_rate'],
                'wht_amount'         => $calc['wht_amount'],
                'net_amount'         => $calc['net_amount'],
                'currency_code'      => $paymentContext['currency_code'] ?? 'SAR',
                'transaction_date'   => $paymentContext['transaction_date'],
                'journal_entry_id'   => $je?->id,
                'notes'              => $paymentContext['notes'] ?? null,
            ]);
        });
    }

    // =========================================================================
    // WHT Certificate
    // =========================================================================

    /**
     * Issue a WHT certificate for a single WHT line.
     * Assigns a certificate number and date.
     */
    public function issueCertificate(WithholdingTaxLine $line, string $certificateDate): WithholdingTaxLine
    {
        if ($line->certificate_number) {
            throw new InvalidArgumentException('Certificate already issued for this line.');
        }

        $line->update([
            'certificate_number' => $this->generateCertificateNumber($line->organization_id),
            'certificate_date'   => $certificateDate,
        ]);

        return $line->fresh();
    }

    // =========================================================================
    // Reporting
    // =========================================================================

    /**
     * WHT summary: total deducted per contact for a period.
     */
    public function summary(int $organizationId, string $from, string $to, string $paymentType = 'payment_made'): Collection
    {
        return WithholdingTaxLine::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('payment_type', $paymentType)
            ->whereBetween('transaction_date', [$from, $to])
            ->select(
                'contact_id',
                'wht_code_id',
                DB::raw('SUM(gross_amount) as total_gross'),
                DB::raw('SUM(wht_amount)  as total_wht'),
                DB::raw('SUM(net_amount)  as total_net'),
                DB::raw('COUNT(*)         as line_count'),
            )
            ->groupBy('contact_id', 'wht_code_id')
            ->with(['whtCode:id,code,name,rate', 'contact:id,name'])
            ->get();
    }

    /**
     * Lines for a specific payment (all WHT lines linked to one payment).
     */
    public function linesForPayment(int $organizationId, string $paymentType, int $paymentId): Collection
    {
        return WithholdingTaxLine::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('payment_type', $paymentType)
            ->where('payment_id', $paymentId)
            ->with(['whtCode:id,code,name,rate'])
            ->get();
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private function postWhtJournal(
        WithholdingTaxCode $code,
        array $paymentContext,
        array $calc,
        array $journalMeta,
        int $organizationId
    ): ?\App\Models\Accounting\JournalEntry {
        $paymentType = $paymentContext['payment_type'];
        $whtAmount   = $calc['wht_amount'];

        // For supplier payments (payment_made): Dr Expense, Cr WHT Payable
        // For customer receipts (payment_received): Dr WHT Receivable, Cr Income
        if ($paymentType === WithholdingTaxLine::TYPE_PAYMENT_MADE) {
            $debitAccount  = $this->fallbackAccount($organizationId, 'other_expense');
            $creditAccount = $code->payable_account_id
                ? $code->payableAccount
                : $this->fallbackAccount($organizationId, 'tax_payable');
        } else {
            $debitAccount  = $code->receivable_account_id
                ? $code->receivableAccount
                : $this->fallbackAccount($organizationId, 'tax_receivable');
            $creditAccount = $this->fallbackAccount($organizationId, 'other_income');
        }

        if (!$debitAccount || !$creditAccount) {
            return null; // GL accounts not configured — skip journal silently
        }

        $lines = [
            [
                'account_id'  => $debitAccount->id,
                'description' => "WHT {$code->code} — debit side",
                'debit'       => $whtAmount,
                'credit'      => 0,
                'line_order'  => 1,
            ],
            [
                'account_id'  => $creditAccount->id,
                'description' => "WHT {$code->code} — credit side",
                'debit'       => 0,
                'credit'      => $whtAmount,
                'line_order'  => 2,
            ],
        ];

        $je = $this->journalService->createEntry(
            array_merge($journalMeta, [
                'organization_id' => $organizationId,
                'description'     => "WHT {$code->code} ({$code->rate}%) on {$paymentType} #{$paymentContext['payment_id']}",
                'reference'       => 'WHT-' . strtoupper($paymentType) . '-' . $paymentContext['payment_id'],
            ]),
            $lines
        );
        $this->journalService->postEntry($je);

        return $je;
    }

    private function fallbackAccount(int $organizationId, string $subType): ?Account
    {
        return Account::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('sub_type', $subType)
            ->where('is_active', true)
            ->where('is_header', false)
            ->orderBy('id')
            ->first();
    }

    private function assertCodeUnique(string $code, int $organizationId): void
    {
        if (WithholdingTaxCode::withTrashed()
            ->where('organization_id', $organizationId)
            ->where('code', $code)
            ->exists()
        ) {
            throw new InvalidArgumentException("WHT code '{$code}' already exists for this organisation.");
        }
    }

    private function generateCertificateNumber(int $organizationId): string
    {
        $count = WithholdingTaxLine::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNotNull('certificate_number')
            ->count() + 1;

        return 'WHTC-' . now()->format('Y') . '-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
