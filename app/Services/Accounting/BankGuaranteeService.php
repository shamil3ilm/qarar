<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\BankGuarantee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankGuaranteeService
{
    /**
     * List guarantees with optional filters.
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = BankGuarantee::query()->orderByDesc('issue_date');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['guarantee_type'])) {
            $query->where('guarantee_type', $filters['guarantee_type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('guarantee_number', 'like', "%{$search}%");
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new bank guarantee.
     */
    public function create(array $data): BankGuarantee
    {
        return DB::transaction(static fn () => BankGuarantee::create($data)->fresh());
    }

    /**
     * Update an existing bank guarantee.
     */
    public function update(BankGuarantee $guarantee, array $data): BankGuarantee
    {
        if (!in_array($guarantee->status, ['draft', 'active'], true)) {
            throw new InvalidArgumentException('Only draft or active guarantees can be updated.');
        }

        DB::transaction(static function () use ($guarantee, $data): void {
            $guarantee->update($data);
        });

        return $guarantee->fresh();
    }

    /**
     * Activate a draft guarantee.
     */
    public function activate(BankGuarantee $guarantee): BankGuarantee
    {
        if ($guarantee->status !== 'draft') {
            throw new InvalidArgumentException('Only draft guarantees can be activated.');
        }

        $guarantee->update(['status' => 'active']);

        return $guarantee->fresh();
    }

    /**
     * Mark a guarantee as expired.
     */
    public function expire(BankGuarantee $guarantee): BankGuarantee
    {
        if ($guarantee->status !== 'active') {
            throw new InvalidArgumentException('Only active guarantees can be expired.');
        }

        $guarantee->update(['status' => 'expired']);

        return $guarantee->fresh();
    }

    /**
     * Record a claim against the guarantee.
     */
    public function claim(BankGuarantee $guarantee, float $amount, string $reason): BankGuarantee
    {
        if (!$guarantee->canClaim()) {
            throw new InvalidArgumentException('This guarantee cannot be claimed in its current state.');
        }

        DB::transaction(static function () use ($guarantee, $amount, $reason): void {
            $guarantee->update([
                'status'       => 'claimed',
                'claim_amount' => $amount,
                'claim_date'   => now()->toDateString(),
                'claim_reason' => $reason,
            ]);
        });

        return $guarantee->fresh();
    }

    /**
     * Return an active guarantee.
     */
    public function return(BankGuarantee $guarantee): BankGuarantee
    {
        if ($guarantee->status !== 'active') {
            throw new InvalidArgumentException('Only active guarantees can be returned.');
        }

        $guarantee->update(['status' => 'returned']);

        return $guarantee->fresh();
    }

    /**
     * Get guarantees expiring within the given number of days.
     */
    public function getExpiringSoon(int $days = 30): Collection
    {
        return BankGuarantee::expiringSoon($days)->orderBy('expiry_date')->get();
    }
}
