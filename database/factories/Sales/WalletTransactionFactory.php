<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        $balanceBefore = fake()->randomFloat(2, 0, 50000);
        $amount = fake()->randomFloat(2, 10, 5000);
        $type = fake()->randomElement([
            WalletTransaction::TYPE_CREDIT,
            WalletTransaction::TYPE_DEBIT,
        ]);
        $balanceAfter = $type === WalletTransaction::TYPE_CREDIT
            ? round($balanceBefore + $amount, 2)
            : round(max(0, $balanceBefore - $amount), 2);

        return [
            'wallet_id' => fake()->randomNumber(3, true),
            'transaction_type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => fake()->sentence(6),
            'source_type' => fake()->randomElement([
                'App\\Models\\Sales\\Invoice',
                'App\\Models\\Sales\\PaymentReceived',
                'App\\Models\\Sales\\CreditNote',
            ]),
            'source_id' => fake()->randomNumber(3, true),
            'reference_number' => fake()->optional(0.4)->bothify('TXN-####??'),
            'transaction_date' => fake()->date(),
            'created_by' => \App\Models\User::factory(),
            'metadata' => null,
        ];
    }

    public function credit(float $amount = null): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $amt = $amount ?? (float) $attributes['amount'];
            $balanceBefore = (float) $attributes['balance_before'];

            return [
                'transaction_type' => WalletTransaction::TYPE_CREDIT,
                'amount' => $amt,
                'balance_after' => round($balanceBefore + $amt, 2),
            ];
        });
    }

    public function debit(float $amount = null): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $amt = $amount ?? (float) $attributes['amount'];
            $balanceBefore = (float) $attributes['balance_before'];

            return [
                'transaction_type' => WalletTransaction::TYPE_DEBIT,
                'amount' => $amt,
                'balance_after' => round(max(0, $balanceBefore - $amt), 2),
            ];
        });
    }

    public function adjustment(): static
    {
        return $this->state(fn () => [
            'transaction_type' => WalletTransaction::TYPE_ADJUSTMENT,
            'description' => 'Balance adjustment',
        ]);
    }
}
