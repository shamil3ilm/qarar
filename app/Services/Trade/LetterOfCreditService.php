<?php

declare(strict_types=1);

namespace App\Services\Trade;

use App\Models\Trade\LcAmendment;
use App\Models\Trade\LetterOfCredit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LetterOfCreditService
{
    /**
     * Create a letter of credit.
     */
    public function create(array $data): LetterOfCredit
    {
        return DB::transaction(function () use ($data) {
            $data['available_amount'] = $data['available_amount'] ?? $data['amount'];

            $lc = LetterOfCredit::create($data);

            return $lc->fresh(['applicant', 'beneficiary', 'bankAccount']);
        });
    }

    /**
     * Update a letter of credit.
     */
    public function update(LetterOfCredit $lc, array $data): LetterOfCredit
    {
        if (!$lc->isEditable()) {
            throw new InvalidArgumentException('Only draft or applied LCs can be updated.');
        }

        return DB::transaction(function () use ($lc, $data) {
            $lc->update($data);
            return $lc->fresh(['applicant', 'beneficiary']);
        });
    }

    /**
     * Issue a letter of credit.
     */
    public function issue(LetterOfCredit $lc, ?string $issueDate = null): LetterOfCredit
    {
        if (!$lc->canIssue()) {
            throw new InvalidArgumentException('This LC cannot be issued in its current status.');
        }

        return DB::transaction(function () use ($lc, $issueDate) {
            $updateData = [
                'status' => LetterOfCredit::STATUS_ISSUED,
            ];

            if ($issueDate) {
                $updateData['issue_date'] = $issueDate;
            }

            $lc->update($updateData);

            return $lc->fresh();
        });
    }

    /**
     * Amend a letter of credit.
     */
    public function amend(LetterOfCredit $lc, array $amendmentData): LcAmendment
    {
        if (!$lc->canAmend()) {
            throw new InvalidArgumentException('This LC cannot be amended in its current status.');
        }

        return DB::transaction(function () use ($lc, $amendmentData) {
            // Get next amendment number
            $nextNumber = ($lc->amendments()->max('amendment_number') ?? 0) + 1;

            // Capture old values for the fields being changed
            $oldValues = [];
            $newValues = $amendmentData['changes'] ?? [];

            foreach ($newValues as $field => $value) {
                $oldValues[$field] = $lc->$field;
            }

            // Create amendment record
            $amendment = $lc->amendments()->create([
                'amendment_number' => $nextNumber,
                'amendment_date' => $amendmentData['amendment_date'] ?? now()->toDateString(),
                'description' => $amendmentData['description'],
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'status' => LcAmendment::STATUS_PENDING,
            ]);

            // Apply changes to the LC
            if (!empty($newValues)) {
                $lc->update($newValues);
            }

            // Recalculate available amount if amount changed
            if (isset($newValues['amount'])) {
                $lc->available_amount = (float) $newValues['amount'] - (float) $lc->utilized_amount;
                $lc->save();
            }

            $lc->update(['status' => LetterOfCredit::STATUS_AMENDED]);

            return $amendment->fresh();
        });
    }

    /**
     * Utilize (draw down) from a letter of credit.
     */
    public function utilize(LetterOfCredit $lc, float $amount, ?string $reference = null): LetterOfCredit
    {
        if (!$lc->canUtilize()) {
            throw new InvalidArgumentException('This LC cannot be utilized in its current status.');
        }

        $maxAmount = $lc->getMaxAmountWithTolerance();
        $newUtilized = (float) $lc->utilized_amount + $amount;

        if ($newUtilized > $maxAmount) {
            throw new InvalidArgumentException(
                "Utilization of {$amount} would exceed the maximum LC amount (including tolerance) of {$maxAmount}."
            );
        }

        return DB::transaction(function () use ($lc, $amount) {
            $lc->utilize($amount);
            return $lc->fresh();
        });
    }

    /**
     * Close a letter of credit.
     */
    public function close(LetterOfCredit $lc): LetterOfCredit
    {
        if (!$lc->canClose()) {
            throw new InvalidArgumentException('This LC cannot be closed in its current status.');
        }

        return DB::transaction(function () use ($lc) {
            $lc->update([
                'status' => LetterOfCredit::STATUS_FULLY_UTILIZED,
                'available_amount' => 0,
            ]);

            return $lc->fresh();
        });
    }

    /**
     * Cancel a letter of credit.
     */
    public function cancel(LetterOfCredit $lc): LetterOfCredit
    {
        if (in_array($lc->status, [LetterOfCredit::STATUS_FULLY_UTILIZED, LetterOfCredit::STATUS_CANCELLED])) {
            throw new InvalidArgumentException('This LC cannot be cancelled.');
        }

        if ((float) $lc->utilized_amount > 0) {
            throw new InvalidArgumentException('Cannot cancel an LC that has been partially utilized.');
        }

        return DB::transaction(function () use ($lc) {
            $lc->update([
                'status' => LetterOfCredit::STATUS_CANCELLED,
            ]);

            return $lc->fresh();
        });
    }

    /**
     * Get available balance of an LC.
     */
    public function getAvailableBalance(LetterOfCredit $lc): array
    {
        return [
            'lc_number' => $lc->lc_number,
            'currency_code' => $lc->currency_code,
            'total_amount' => (float) $lc->amount,
            'tolerance_percent' => (float) $lc->tolerance_percent,
            'max_amount' => $lc->getMaxAmountWithTolerance(),
            'utilized_amount' => (float) $lc->utilized_amount,
            'available_amount' => (float) $lc->available_amount,
            'status' => $lc->status,
            'expiry_date' => $lc->expiry_date->toDateString(),
            'is_expired' => $lc->isExpired(),
        ];
    }

    /**
     * Get LCs expiring within a certain number of days.
     */
    public function getExpiringLCs(int $days = 30): Collection
    {
        return LetterOfCredit::expiringSoon($days)
            ->with(['applicant:id,name', 'beneficiary:id,name'])
            ->orderBy('expiry_date')
            ->get();
    }
}
