<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorCreditNote;
use App\Models\Purchase\VendorCreditNoteLine;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VendorCreditNoteService
{
    public function __construct(
        private JournalService $journalService,
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * List vendor credit notes with optional filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = VendorCreditNote::with(['vendor', 'bill'])
            ->orderByDesc('credit_date');

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('credit_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('credit_date', '<=', $filters['end_date']);
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->paginate($perPage);
    }

    /**
     * Create a vendor credit note with lines.
     */
    public function create(array $data, array $lines): VendorCreditNote
    {
        return DB::transaction(function () use ($data, $lines): VendorCreditNote {
            if (empty($data['credit_note_number'])) {
                $data['credit_note_number'] = $this->numberGenerator->generate('VCN');
            }

            $data['status'] = VendorCreditNote::STATUS_DRAFT;

            $creditNote = VendorCreditNote::create($data);

            $subtotal = '0';
            $taxTotal = '0';

            foreach ($lines as $lineData) {
                $qty = (string) ($lineData['quantity'] ?? 1);
                $price = (string) ($lineData['unit_price'] ?? 0);
                $taxRate = (string) ($lineData['tax_rate'] ?? 0);

                $lineSubtotal = bcmul($qty, $price, 4);
                $lineTax = bcmul($lineSubtotal, bcdiv($taxRate, '100', 6), 4);
                $lineTotal = bcadd($lineSubtotal, $lineTax, 4);

                $creditNote->lines()->create([
                    'organization_id' => $creditNote->organization_id,
                    'product_id' => $lineData['product_id'] ?? null,
                    'description' => $lineData['description'] ?? '',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ]);

                $subtotal = bcadd($subtotal, $lineSubtotal, 4);
                $taxTotal = bcadd($taxTotal, $lineTax, 4);
            }

            $total = bcadd($subtotal, $taxTotal, 4);

            $creditNote->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxTotal,
                'total_amount' => $total,
            ]);

            return $creditNote->load('lines');
        });
    }

    /**
     * Update a draft credit note.
     */
    public function update(VendorCreditNote $creditNote, array $data, ?array $lines = null): VendorCreditNote
    {
        if ($creditNote->status !== VendorCreditNote::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft credit notes can be updated.');
        }

        return DB::transaction(function () use ($creditNote, $data, $lines): VendorCreditNote {
            $creditNote->update(collect($data)->except(['lines'])->toArray());

            if ($lines !== null) {
                $creditNote->lines()->delete();
                $subtotal = '0';
                $taxTotal = '0';

                foreach ($lines as $lineData) {
                    $qty = (string) ($lineData['quantity'] ?? 1);
                    $price = (string) ($lineData['unit_price'] ?? 0);
                    $taxRate = (string) ($lineData['tax_rate'] ?? 0);

                    $lineSubtotal = bcmul($qty, $price, 4);
                    $lineTax = bcmul($lineSubtotal, bcdiv($taxRate, '100', 6), 4);
                    $lineTotal = bcadd($lineSubtotal, $lineTax, 4);

                    $creditNote->lines()->create([
                        'organization_id' => $creditNote->organization_id,
                        'product_id' => $lineData['product_id'] ?? null,
                        'description' => $lineData['description'] ?? '',
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $lineTax,
                        'line_total' => $lineTotal,
                    ]);

                    $subtotal = bcadd($subtotal, $lineSubtotal, 4);
                    $taxTotal = bcadd($taxTotal, $lineTax, 4);
                }

                $creditNote->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxTotal,
                    'total_amount' => bcadd($subtotal, $taxTotal, 4),
                ]);
            }

            return $creditNote->fresh('lines');
        });
    }

    /**
     * Delete (soft-delete) a draft credit note.
     */
    public function delete(VendorCreditNote $creditNote): void
    {
        if ($creditNote->status !== VendorCreditNote::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft credit notes can be deleted.');
        }

        $creditNote->lines()->delete();
        $creditNote->delete();
    }

    /**
     * Post a vendor credit note and create the journal entry.
     * DR Accounts Payable / CR Purchase Returns.
     */
    public function post(VendorCreditNote $creditNote): VendorCreditNote
    {
        if ($creditNote->status !== VendorCreditNote::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft credit notes can be posted.');
        }

        return DB::transaction(function () use ($creditNote): VendorCreditNote {
            $payableAccountId = config('erp.default_accounts.payable');
            $purchaseReturnsAccountId = config('erp.default_accounts.purchase_returns', config('erp.default_accounts.payable'));

            try {
                if ($payableAccountId && $purchaseReturnsAccountId) {
                    $this->journalService->createEntry([
                        'organization_id' => $creditNote->organization_id,
                        'entry_date' => $creditNote->credit_date->toDateString(),
                        'reference' => $creditNote->credit_note_number,
                        'description' => "Vendor Credit Note {$creditNote->credit_note_number}",
                        'source_type' => VendorCreditNote::class,
                        'source_id' => $creditNote->id,
                        'currency_code' => 'SAR',
                    ], [
                        [
                            'account_id' => $payableAccountId,
                            'debit' => (float) $creditNote->total_amount,
                            'credit' => 0,
                            'description' => "AP Credit - {$creditNote->credit_note_number}",
                        ],
                        [
                            'account_id' => $purchaseReturnsAccountId,
                            'debit' => 0,
                            'credit' => (float) $creditNote->total_amount,
                            'description' => "Purchase Return - {$creditNote->credit_note_number}",
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Vendor credit note journal entry creation skipped: ' . $e->getMessage()
                );
            }

            $creditNote->update([
                'status' => VendorCreditNote::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $creditNote->fresh();
        });
    }

    /**
     * Apply (part of) a credit note to a bill.
     */
    public function apply(VendorCreditNote $creditNote, Bill $bill, float $amount): void
    {
        if ($creditNote->status !== VendorCreditNote::STATUS_POSTED) {
            throw new InvalidArgumentException('Only posted credit notes can be applied.');
        }

        $remaining = $creditNote->getRemainingAmount();
        if ($amount > $remaining) {
            throw new InvalidArgumentException(
                "Cannot apply {$amount}. Only {$remaining} remaining on this credit note."
            );
        }

        if ((float) $bill->amount_due <= 0) {
            throw new InvalidArgumentException("Bill has no outstanding balance.");
        }

        $applyAmount = min($amount, (float) $bill->amount_due);

        DB::transaction(function () use ($creditNote, $bill, $applyAmount): void {
            $newApplied = bcadd((string) $creditNote->applied_amount, (string) $applyAmount, 4);
            $isFullyApplied = bccomp($newApplied, (string) $creditNote->total_amount, 4) >= 0;

            $creditNote->update([
                'applied_amount' => $newApplied,
                'status' => $isFullyApplied ? VendorCreditNote::STATUS_APPLIED : VendorCreditNote::STATUS_POSTED,
            ]);

            $bill->recordPayment($applyAmount);
        });
    }

    /**
     * Void a vendor credit note.
     */
    public function void(VendorCreditNote $creditNote): VendorCreditNote
    {
        if ($creditNote->status === VendorCreditNote::STATUS_VOID) {
            throw new InvalidArgumentException('Credit note is already voided.');
        }

        if ($creditNote->status === VendorCreditNote::STATUS_APPLIED) {
            throw new InvalidArgumentException('Fully applied credit notes cannot be voided.');
        }

        $creditNote->update([
            'status' => VendorCreditNote::STATUS_VOID,
            'voided_by' => auth()->id(),
            'voided_at' => now(),
        ]);

        return $creditNote->fresh();
    }
}
