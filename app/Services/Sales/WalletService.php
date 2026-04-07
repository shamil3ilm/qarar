<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\Wallet;
use App\Models\Sales\WalletTransaction;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getOrCreateWallet(int $organizationId, int $contactId, string $walletType, string $currencyCode): Wallet
    {
        return Wallet::firstOrCreate(
            [
                'organization_id' => $organizationId,
                'contact_id' => $contactId,
                'currency_code' => $currencyCode,
            ],
            [
                'wallet_type' => $walletType,
                'balance' => 0,
                'credit_limit' => 0,
                'is_active' => true,
            ]
        );
    }

    public function credit(
        Wallet $wallet,
        float $amount,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ApiException::fromError(ErrorCodes::VALIDATION_INVALID_AMOUNT, [
                'amount' => 'Credit amount must be positive.',
            ]);
        }

        return DB::transaction(function () use ($wallet, $amount, $description, $sourceType, $sourceId) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
            return $wallet->credit($amount, $description, $sourceType, $sourceId);
        });
    }

    public function debit(
        Wallet $wallet,
        float $amount,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ApiException::fromError(ErrorCodes::VALIDATION_INVALID_AMOUNT, [
                'amount' => 'Debit amount must be positive.',
            ]);
        }

        return DB::transaction(function () use ($wallet, $amount, $description, $sourceType, $sourceId) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if (! $wallet->hasBalance($amount)) {
                throw ApiException::fromError(ErrorCodes::BIZ_INSUFFICIENT_BALANCE);
            }

            return $wallet->debit($amount, $description, $sourceType, $sourceId);
        });
    }

    public function transfer(
        Wallet $from,
        Wallet $to,
        float $amount,
        string $description
    ): array {
        if ($from->currency_code !== $to->currency_code) {
            throw ApiException::fromError(ErrorCodes::VALIDATION_INVALID_AMOUNT, [
                'currency' => 'Cannot transfer between wallets with different currencies.',
            ]);
        }

        return DB::transaction(function () use ($from, $to, $amount, $description) {
            $debit = $this->debit($from, $amount, "Transfer out: {$description}", Wallet::class, $to->id);
            $credit = $this->credit($to, $amount, "Transfer in: {$description}", Wallet::class, $from->id);

            return ['debit' => $debit, 'credit' => $credit];
        });
    }

    public function adjustBalance(
        Wallet $wallet,
        float $amount,
        string $description,
        int $userId
    ): WalletTransaction {
        if (bccomp((string) $amount, '0', 4) === 0) {
            throw new \InvalidArgumentException('Adjustment amount cannot be zero.');
        }

        return DB::transaction(function () use ($wallet, $amount, $description) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = (float) $wallet->balance;
            $wallet->balance = bcadd((string) $wallet->balance, (string) $amount, 2);
            $wallet->save();

            return $wallet->transactions()->create([
                'transaction_type' => 'adjustment',
                'amount' => abs($amount),
                'balance_before' => $balanceBefore,
                'balance_after' => (float) $wallet->balance,
                'description' => $description,
            ]);
        });
    }

    public function getStatement(
        Wallet $wallet,
        ?string $fromDate = null,
        ?string $toDate = null,
        int $perPage = 20
    ) {
        $query = $wallet->transactions()
            ->orderBy('created_at', 'desc');

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query->paginate($perPage);
    }

    public function getBalance(int $organizationId, int $contactId, ?string $currencyCode = null): array
    {
        $query = Wallet::where('organization_id', $organizationId)
            ->where('contact_id', $contactId)
            ->active();

        if ($currencyCode) {
            $query->where('currency_code', $currencyCode);
        }

        return $query->get()->map(fn (Wallet $wallet) => [
            'id' => $wallet->id,
            'currency' => $wallet->currency_code,
            'balance' => $wallet->balance,
            'credit_limit' => $wallet->credit_limit,
            'available_credit' => $wallet->getAvailableCredit(),
        ])->toArray();
    }
}
