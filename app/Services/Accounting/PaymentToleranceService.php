<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\PaymentDifferencePost;
use App\Models\Accounting\PaymentToleranceGroup;
use App\Models\Accounting\PaymentToleranceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Payment Tolerance & Clearing Variance Service (SAP FI OBA3 / OBB8 equivalent).
 *
 * Responsibilities:
 *  - Manage tolerance groups and per-currency thresholds
 *  - Evaluate whether a payment/invoice difference is within tolerance
 *  - Auto-clear differences (write off to GL) when within tolerance
 *  - Provide variance reports
 */
class PaymentToleranceService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    // =========================================================================
    // Tolerance Group management
    // =========================================================================

    public function listGroups(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = PaymentToleranceGroup::where('organization_id', $organizationId)
            ->with('items')
            ->orderBy('code');

        if (!empty($filters['applies_to'])) {
            $query->where('applies_to', $filters['applies_to']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function createGroup(array $data, int $organizationId): PaymentToleranceGroup
    {
        $this->assertCodeUnique($data['code'], $organizationId);

        return DB::transaction(function () use ($data, $organizationId): PaymentToleranceGroup {
            // If new group is marked default, unset existing default
            if (!empty($data['is_default'])) {
                PaymentToleranceGroup::where('organization_id', $organizationId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $groupData = array_diff_key($data, ['items' => null]);
            $group = PaymentToleranceGroup::create(
                array_merge($groupData, ['organization_id' => $organizationId])
            );

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $group->items()->create($item);
                }
            }

            return $group->load('items');
        });
    }

    public function updateGroup(PaymentToleranceGroup $group, array $data): PaymentToleranceGroup
    {
        if (isset($data['code']) && $data['code'] !== $group->code) {
            $this->assertCodeUnique($data['code'], $group->organization_id);
        }

        return DB::transaction(function () use ($group, $data): PaymentToleranceGroup {
            if (!empty($data['is_default'])) {
                PaymentToleranceGroup::where('organization_id', $group->organization_id)
                    ->where('is_default', true)
                    ->where('id', '!=', $group->id)
                    ->update(['is_default' => false]);
            }

            $group->update($data);

            return $group->fresh('items');
        });
    }

    public function deleteGroup(PaymentToleranceGroup $group): void
    {
        if ($group->differencePosts()->exists()) {
            throw new InvalidArgumentException('Cannot delete a tolerance group that has been applied to payments.');
        }
        $group->items()->delete();
        $group->delete();
    }

    // =========================================================================
    // Tolerance Item (per-currency threshold) management
    // =========================================================================

    public function upsertItem(PaymentToleranceGroup $group, array $data): PaymentToleranceItem
    {
        return PaymentToleranceItem::updateOrCreate(
            [
                'tolerance_group_id' => $group->id,
                'currency_code'      => strtoupper($data['currency_code']),
            ],
            $data
        );
    }

    public function removeItem(PaymentToleranceItem $item): void
    {
        $item->delete();
    }

    // =========================================================================
    // Core: evaluate a payment difference
    // =========================================================================

    /**
     * Evaluate whether a payment/invoice difference is within tolerance.
     *
     * @return array{
     *   within_tolerance: bool,
     *   difference_amount: float,
     *   difference_type: string,
     *   tolerance_item: PaymentToleranceItem|null,
     *   max_allowed_abs: float,
     *   max_allowed_pct: float
     * }
     */
    public function evaluate(
        PaymentToleranceGroup $group,
        float $invoiceAmount,
        float $paymentAmount,
        string $currencyCode
    ): array {
        $diff = round($paymentAmount - $invoiceAmount, 4);
        $differenceType = $diff < 0
            ? PaymentDifferencePost::TYPE_UNDERPAYMENT
            : PaymentDifferencePost::TYPE_OVERPAYMENT;
        $absDiff = abs($diff);

        $item = $group->itemForCurrency($currencyCode);

        if ($item === null || $absDiff < 0.00005) {
            return [
                'within_tolerance' => $absDiff < 0.00005,
                'difference_amount' => $diff,
                'difference_type'   => $differenceType,
                'tolerance_item'    => $item,
                'max_allowed_abs'   => 0.0,
                'max_allowed_pct'   => 0.0,
            ];
        }

        $within = $differenceType === PaymentDifferencePost::TYPE_UNDERPAYMENT
            ? $item->isUnderpayWithin($absDiff, $invoiceAmount)
            : $item->isOverpayWithin($absDiff, $invoiceAmount);

        return [
            'within_tolerance' => $within,
            'difference_amount' => $diff,
            'difference_type'   => $differenceType,
            'tolerance_item'    => $item,
            'max_allowed_abs'   => $differenceType === PaymentDifferencePost::TYPE_UNDERPAYMENT
                ? (float) $item->underpay_abs
                : (float) $item->overpay_abs,
            'max_allowed_pct'   => $differenceType === PaymentDifferencePost::TYPE_UNDERPAYMENT
                ? (float) $item->underpay_pct
                : (float) $item->overpay_pct,
        ];
    }

    // =========================================================================
    // Clear: auto-post a tolerance difference to GL
    // =========================================================================

    /**
     * Auto-clear a payment difference that is within tolerance.
     * Posts the write-off GL entry and records the difference post.
     *
     * @param  array  $paymentContext  [payment_type, payment_id, contact_id?, document_type?, document_id?, organization_id, posting_date, currency_code, notes?]
     * @param  array  $journalMeta
     */
    public function clearDifference(
        PaymentToleranceGroup $group,
        float $invoiceAmount,
        float $paymentAmount,
        array $paymentContext,
        array $journalMeta
    ): PaymentDifferencePost {
        $evaluation = $this->evaluate($group, $invoiceAmount, $paymentAmount, $paymentContext['currency_code']);

        if (!$evaluation['within_tolerance']) {
            throw new InvalidArgumentException(
                sprintf(
                    'Difference %.4f %s exceeds tolerance. Max allowed: %.4f (abs) or %.2f%% of invoice.',
                    abs($evaluation['difference_amount']),
                    $paymentContext['currency_code'],
                    $evaluation['max_allowed_abs'],
                    $evaluation['max_allowed_pct']
                )
            );
        }

        return DB::transaction(function () use ($group, $invoiceAmount, $paymentAmount, $paymentContext, $journalMeta, $evaluation): PaymentDifferencePost {
            $organizationId = (int) $paymentContext['organization_id'];
            $diff           = $evaluation['difference_amount'];
            $diffType       = $evaluation['difference_type'];
            $item           = $evaluation['tolerance_item'];

            $je = $this->postDifferenceJournal(
                $group, $item, $diff, $diffType, $paymentContext, $journalMeta, $organizationId
            );

            return PaymentDifferencePost::create([
                'organization_id'   => $organizationId,
                'tolerance_group_id' => $group->id,
                'payment_type'      => $paymentContext['payment_type'],
                'payment_id'        => $paymentContext['payment_id'],
                'contact_id'        => $paymentContext['contact_id'] ?? null,
                'document_type'     => $paymentContext['document_type'] ?? null,
                'document_id'       => $paymentContext['document_id'] ?? null,
                'currency_code'     => $paymentContext['currency_code'],
                'invoice_amount'    => $invoiceAmount,
                'payment_amount'    => $paymentAmount,
                'difference_amount' => $diff,
                'difference_type'   => $diffType,
                'resolution'        => PaymentDifferencePost::RES_WRITTEN_OFF,
                'journal_entry_id'  => $je?->id,
                'posting_date'      => $paymentContext['posting_date'],
                'notes'             => $paymentContext['notes'] ?? null,
                'created_by'        => $paymentContext['created_by'] ?? null,
            ]);
        });
    }

    // =========================================================================
    // Reporting
    // =========================================================================

    /**
     * Variance summary: total written off per period, grouped by direction.
     */
    public function varianceSummary(int $organizationId, string $from, string $to): Collection
    {
        return PaymentDifferencePost::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereBetween('posting_date', [$from, $to])
            ->select(
                'difference_type',
                'currency_code',
                DB::raw('SUM(ABS(difference_amount)) as total_variance'),
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(ABS(difference_amount)) as avg_variance'),
            )
            ->groupBy('difference_type', 'currency_code')
            ->get();
    }

    public function listDifferencePosts(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = PaymentDifferencePost::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['toleranceGroup:id,code,name', 'contact:id,company_name,contact_name'])
            ->orderByDesc('posting_date');

        if (!empty($filters['payment_type'])) {
            $query->where('payment_type', $filters['payment_type']);
        }
        if (!empty($filters['from'])) {
            $query->where('posting_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('posting_date', '<=', $filters['to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private function postDifferenceJournal(
        PaymentToleranceGroup $group,
        ?PaymentToleranceItem $item,
        float $diff,
        string $diffType,
        array $paymentContext,
        array $journalMeta,
        int $organizationId
    ): ?\App\Models\Accounting\JournalEntry {
        $absDiff = abs($diff);

        // Determine GL accounts: use item's specific accounts, fall back to sub_type lookup
        if ($diffType === PaymentDifferencePost::TYPE_UNDERPAYMENT) {
            $debitAccount  = $item?->underpay_gl_account_id
                ? $item->underpayGlAccount
                : $this->fallbackAccount($organizationId, 'other_expense');
            $creditAccount = $this->fallbackAccount($organizationId, 'receivable');
        } else {
            $debitAccount  = $this->fallbackAccount($organizationId, 'payable');
            $creditAccount = $item?->overpay_gl_account_id
                ? $item->overpayGlAccount
                : $this->fallbackAccount($organizationId, 'other_income');
        }

        if (!$debitAccount || !$creditAccount) {
            return null;
        }

        $label = $diffType === PaymentDifferencePost::TYPE_UNDERPAYMENT
            ? 'Underpayment write-off'
            : 'Overpayment credit';

        $lines = [
            [
                'account_id'  => $debitAccount->id,
                'description' => "{$label} — tolerance group {$group->code}",
                'debit'       => $absDiff,
                'credit'      => 0,
                'line_order'  => 1,
            ],
            [
                'account_id'  => $creditAccount->id,
                'description' => "{$label} — tolerance group {$group->code}",
                'debit'       => 0,
                'credit'      => $absDiff,
                'line_order'  => 2,
            ],
        ];

        $je = $this->journalService->createEntry(
            array_merge($journalMeta, [
                'organization_id' => $organizationId,
                'description'     => "{$label} {$paymentContext['currency_code']} {$absDiff} ({$paymentContext['payment_type']} #{$paymentContext['payment_id']})",
                'reference'       => 'TOL-' . strtoupper($diffType[0]) . '-' . $paymentContext['payment_id'],
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
        if (PaymentToleranceGroup::withTrashed()
            ->where('organization_id', $organizationId)
            ->where('code', $code)
            ->exists()
        ) {
            throw new InvalidArgumentException("Tolerance group code '{$code}' already exists for this organisation.");
        }
    }
}
