<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\BulkSaleBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BulkSaleBatchFactory extends Factory
{
    protected $model = BulkSaleBatch::class;

    public function definition(): array
    {
        $totalInvoices = fake()->numberBetween(5, 50);
        $totalSubtotal = fake()->randomFloat(2, 1000, 100000);
        $totalDiscount = round($totalSubtotal * fake()->randomFloat(2, 0, 10) / 100, 2);
        $totalTax = round(($totalSubtotal - $totalDiscount) * fake()->randomElement([5, 10, 15]) / 100, 2);
        $totalAmount = round($totalSubtotal - $totalDiscount + $totalTax, 2);

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'batch_number' => 'BSB-' . fake()->unique()->numerify('######'),
            'name' => fake()->words(3, true) . ' Batch',
            'sale_date' => fake()->dateTimeBetween('-7 days', 'now'),
            'original_sale_date' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'total_invoices' => $totalInvoices,
            'total_subtotal' => $totalSubtotal,
            'total_discount' => $totalDiscount,
            'total_tax' => $totalTax,
            'total_amount' => $totalAmount,
            'status' => BulkSaleBatch::STATUS_DRAFT,
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'errors' => null,
            'started_at' => null,
            'completed_at' => null,
            'auto_post' => false,
            'auto_send_email' => false,
            'generate_receipts' => true,
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'credit_card']),
            'bank_account_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => BulkSaleBatch::STATUS_DRAFT,
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BulkSaleBatch::STATUS_PROCESSING,
            'started_at' => now(),
            'processed_count' => (int) round(($attributes['total_invoices'] ?? 10) / 2),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BulkSaleBatch::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'processed_count' => $attributes['total_invoices'] ?? 10,
            'success_count' => $attributes['total_invoices'] ?? 10,
            'failed_count' => 0,
        ]);
    }

    public function partiallyCompleted(): static
    {
        return $this->state(function (array $attributes) {
            $total = $attributes['total_invoices'] ?? 10;
            $failed = fake()->numberBetween(1, max(1, (int) ($total / 3)));
            $success = $total - $failed;

            return [
                'status' => BulkSaleBatch::STATUS_PARTIALLY_COMPLETED,
                'started_at' => now()->subMinutes(10),
                'completed_at' => now(),
                'processed_count' => $total,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => [['line' => 1, 'message' => 'Invalid customer ID']],
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BulkSaleBatch::STATUS_FAILED,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'processed_count' => $attributes['total_invoices'] ?? 10,
            'success_count' => 0,
            'failed_count' => $attributes['total_invoices'] ?? 10,
            'errors' => [['message' => 'Batch processing failed due to system error']],
        ]);
    }

    public function backdated(): static
    {
        return $this->state(fn () => [
            'sale_date' => now(),
            'original_sale_date' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function withAutoPost(): static
    {
        return $this->state(fn () => [
            'auto_post' => true,
            'auto_send_email' => true,
        ]);
    }
}
