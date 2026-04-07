<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\TdsEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for recording and managing TDS deductions stored in the
 * tds_deductions table (migration 2026_03_25_000002).
 *
 * Distinct from the existing TdsService, which manages TDS sections,
 * certificates, quarterly returns (Form 26Q), and TCS via the prior schema.
 */
class TdsComplianceService
{
    /**
     * Record a new TDS deduction entry.
     *
     * @param  array  $data  Validated payload including organization_id, tds_amount, etc.
     * @return TdsEntry
     */
    public function recordDeduction(array $data): TdsEntry
    {
        return DB::transaction(function () use ($data): TdsEntry {
            return TdsEntry::create($data);
        });
    }

    /**
     * Mark a TDS deduction as deposited with a challan number.
     *
     * @param  TdsEntry  $deduction
     * @param  string    $challanNumber
     * @return TdsEntry
     *
     * @throws \RuntimeException if the deduction is already deposited.
     */
    public function depositChallan(TdsEntry $deduction, string $challanNumber): TdsEntry
    {
        if ($deduction->status === 'deposited') {
            throw new \RuntimeException('TDS deduction has already been deposited.');
        }

        $deduction->update([
            'challan_number' => $challanNumber,
            'deposited_at'   => now(),
            'status'         => 'deposited',
        ]);

        return $deduction->fresh();
    }

    /**
     * Get all pending (not yet deposited) TDS deductions for an organization.
     *
     * @param  Organization  $org
     * @return Collection<int, TdsEntry>
     */
    public function getPendingDeductions(Organization $org): Collection
    {
        return TdsEntry::withoutGlobalScope('organization')
            ->where('organization_id', $org->id)
            ->where('status', 'pending')
            ->orderBy('transaction_date')
            ->get();
    }
}
