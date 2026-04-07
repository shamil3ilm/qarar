<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\HouseBank;
use App\Models\Accounting\HouseBankAccount;
use App\Models\Accounting\PaymentAdvice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * House Bank & Payment Advice Service (SAP FI-BL FI12 / FBZP).
 *
 * Responsibilities:
 *  - Manage house bank master data (FI12)
 *  - Manage bank account assignments within a house bank
 *  - Create / send / acknowledge / cancel payment advices
 *  - Retrieve advice history and outstanding advices
 */
class HouseBankService
{
    // =========================================================================
    // House Banks
    // =========================================================================

    public function listBanks(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = HouseBank::where('organization_id', $organizationId)
            ->with('accounts')
            ->orderBy('code');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function createBank(array $data, int $organizationId): HouseBank
    {
        if ($data['is_default'] ?? false) {
            $this->clearDefault($organizationId);
        }

        return HouseBank::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    public function updateBank(HouseBank $bank, array $data): HouseBank
    {
        if (($data['is_default'] ?? false) && !$bank->is_default) {
            $this->clearDefault($bank->organization_id);
        }

        $bank->update($data);

        return $bank->fresh('accounts');
    }

    public function deleteBank(HouseBank $bank): void
    {
        if ($bank->paymentAdvices()->whereNotIn('status', [PaymentAdvice::STATUS_CANCELLED])->exists()) {
            throw new InvalidArgumentException('Cannot delete a house bank with active payment advices.');
        }

        $bank->delete();
    }

    // =========================================================================
    // House Bank Accounts
    // =========================================================================

    public function addAccount(HouseBank $bank, array $data): HouseBankAccount
    {
        return HouseBankAccount::create(array_merge($data, ['house_bank_id' => $bank->id]));
    }

    public function updateAccount(HouseBankAccount $account, array $data): HouseBankAccount
    {
        $account->update($data);

        return $account->fresh();
    }

    public function removeAccount(HouseBankAccount $account): void
    {
        $account->delete();
    }

    // =========================================================================
    // Payment Advices
    // =========================================================================

    public function listAdvices(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = PaymentAdvice::where('organization_id', $organizationId)
            ->with(['houseBank:id,code,name', 'houseBankAccount:id,account_id_code,currency_code'])
            ->orderByDesc('payment_date');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }
        if (!empty($filters['house_bank_id'])) {
            $query->where('house_bank_id', (int) $filters['house_bank_id']);
        }
        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', (int) $filters['contact_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('payment_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('payment_date', '<=', $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function createAdvice(array $data, int $organizationId): PaymentAdvice
    {
        return DB::transaction(function () use ($data, $organizationId): PaymentAdvice {
            $adviceNumber = $data['advice_number'] ?? $this->generateAdviceNumber($organizationId);

            $advice = PaymentAdvice::create(array_merge($data, [
                'organization_id' => $organizationId,
                'advice_number'   => $adviceNumber,
                'status'          => PaymentAdvice::STATUS_DRAFT,
            ]));

            return $advice->load(['houseBank:id,code,name', 'houseBankAccount:id,account_id_code,currency_code']);
        });
    }

    public function sendAdvice(PaymentAdvice $advice): PaymentAdvice
    {
        if ($advice->status !== PaymentAdvice::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft advices can be sent.');
        }

        $advice->update([
            'status'  => PaymentAdvice::STATUS_SENT,
            'sent_at' => now(),
        ]);

        return $advice->fresh();
    }

    public function acknowledgeAdvice(PaymentAdvice $advice): PaymentAdvice
    {
        if ($advice->status !== PaymentAdvice::STATUS_SENT) {
            throw new InvalidArgumentException('Only sent advices can be acknowledged.');
        }

        $advice->update([
            'status'          => PaymentAdvice::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
        ]);

        return $advice->fresh();
    }

    public function cancelAdvice(PaymentAdvice $advice, string $reason = ''): PaymentAdvice
    {
        if ($advice->status === PaymentAdvice::STATUS_ACKNOWLEDGED) {
            throw new InvalidArgumentException('Cannot cancel an acknowledged advice.');
        }

        $narration = $reason
            ? ($advice->narration ? $advice->narration . ' | Cancelled: ' . $reason : 'Cancelled: ' . $reason)
            : $advice->narration;

        $advice->update([
            'status'    => PaymentAdvice::STATUS_CANCELLED,
            'narration' => $narration,
        ]);

        return $advice->fresh();
    }

    // =========================================================================
    // Reporting
    // =========================================================================

    /**
     * Outstanding (draft + sent) advices grouped by house bank.
     */
    public function outstandingSummary(int $organizationId): Collection
    {
        return PaymentAdvice::where('organization_id', $organizationId)
            ->whereIn('status', [PaymentAdvice::STATUS_DRAFT, PaymentAdvice::STATUS_SENT])
            ->selectRaw('house_bank_id, direction, currency_code, SUM(amount) as total_amount, COUNT(*) as advice_count')
            ->groupBy('house_bank_id', 'direction', 'currency_code')
            ->with('houseBank:id,code,name')
            ->get();
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private function clearDefault(int $organizationId): void
    {
        HouseBank::where('organization_id', $organizationId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function generateAdviceNumber(int $organizationId): string
    {
        $count = PaymentAdvice::where('organization_id', $organizationId)->count() + 1;

        return 'PADV-' . now()->format('Ymd') . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
