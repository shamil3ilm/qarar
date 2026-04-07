<?php

declare(strict_types=1);

namespace App\Services\Customs;

use App\Models\Customs\ExciseCategory;
use App\Models\Customs\ExciseDeclaration;
use App\Models\Customs\ExciseDeclarationItem;
use App\Models\Customs\ExciseRate;
use App\Models\Customs\ProductExciseMapping;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExciseService
{
    /**
     * Create an excise category.
     */
    public function createCategory(array $data): ExciseCategory
    {
        return DB::transaction(function () use ($data) {
            return ExciseCategory::create($data);
        });
    }

    /**
     * Create an excise rate for a category.
     */
    public function createRate(array $data): ExciseRate
    {
        return DB::transaction(function () use ($data) {
            return ExciseRate::create($data);
        });
    }

    /**
     * Map a product to an excise category.
     */
    public function mapProduct(array $data): ProductExciseMapping
    {
        return DB::transaction(function () use ($data) {
            return ProductExciseMapping::updateOrCreate(
                [
                    'product_id' => $data['product_id'],
                    'excise_category_id' => $data['excise_category_id'],
                ],
                [
                    'excise_rate_id' => $data['excise_rate_id'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                ]
            );
        });
    }

    /**
     * Create an excise declaration.
     */
    public function createDeclaration(array $data): ExciseDeclaration
    {
        return DB::transaction(function () use ($data) {
            $declaration = ExciseDeclaration::create($data);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $itemData['declaration_id'] = $declaration->id;

                    // Auto-calculate excise if rate provided
                    if (!empty($itemData['excise_rate_id']) && empty($itemData['excise_amount'])) {
                        $rate = ExciseRate::find($itemData['excise_rate_id']);
                        if ($rate) {
                            $exciseValue = (float) ($itemData['excisable_value'] ?? 0);
                            $quantity = (float) ($itemData['quantity'] ?? 0);
                            $itemData['excise_rate_applied'] = $rate->rate_percent ?? 0;
                            $itemData['excise_amount'] = $rate->calculateExcise($exciseValue, $quantity);
                        }
                    }

                    $declaration->items()->create($itemData);
                }

                $declaration->recalculateTotals();
            }

            return $declaration->fresh(['items', 'items.exciseCategory']);
        });
    }

    /**
     * Calculate excise for a product/value combination.
     */
    public function calculateExcise(int $productId, float $value, float $quantity, ?string $date = null): array
    {
        $mapping = ProductExciseMapping::where('product_id', $productId)
            ->where('is_active', true)
            ->with(['exciseCategory', 'exciseRate'])
            ->first();

        if (!$mapping) {
            return [
                'excisable' => false,
                'excise_amount' => 0,
                'rate' => null,
                'category' => null,
            ];
        }

        $rate = $mapping->exciseRate;

        // If no specific rate mapped, get current rate from category
        if (!$rate) {
            $rate = $mapping->exciseCategory->getCurrentRate($date);
        }

        if (!$rate) {
            return [
                'excisable' => true,
                'excise_amount' => 0,
                'rate' => null,
                'category' => $mapping->exciseCategory->name,
                'message' => 'No effective rate found for the given date.',
            ];
        }

        $exciseAmount = $rate->calculateExcise($value, $quantity);

        return [
            'excisable' => true,
            'excise_amount' => $exciseAmount,
            'rate_type' => $rate->rate_type,
            'rate_percent' => $rate->rate_percent,
            'specific_amount' => $rate->specific_amount,
            'category' => $mapping->exciseCategory->name,
            'category_code' => $mapping->exciseCategory->code,
        ];
    }

    /**
     * Submit an excise declaration.
     */
    public function submitDeclaration(ExciseDeclaration $declaration): ExciseDeclaration
    {
        if (!$declaration->canSubmit()) {
            throw new InvalidArgumentException('Declaration cannot be submitted. Ensure it is in draft status with items.');
        }

        return DB::transaction(function () use ($declaration) {
            $declaration->update([
                'status' => ExciseDeclaration::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Pay an excise declaration.
     */
    public function payDeclaration(ExciseDeclaration $declaration, array $paymentData = []): ExciseDeclaration
    {
        if (!$declaration->canPay()) {
            throw new InvalidArgumentException('Only submitted declarations can be paid.');
        }

        return DB::transaction(function () use ($declaration, $paymentData) {
            $updateData = [
                'status' => ExciseDeclaration::STATUS_PAID,
                'paid_at' => now(),
            ];

            if (isset($paymentData['payment_reference'])) {
                $updateData['payment_reference'] = $paymentData['payment_reference'];
            }

            if (isset($paymentData['journal_entry_id'])) {
                $updateData['journal_entry_id'] = $paymentData['journal_entry_id'];
            }

            $declaration->update($updateData);

            return $declaration->fresh();
        });
    }

    /**
     * Get excise declarations with optional filters.
     */
    public function getDeclarations(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ExciseDeclaration::with(['createdBy:id,name'])
            ->orderByDesc('period_from')
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->forStatus($filters['status']);
        }

        if (!empty($filters['period_from']) && !empty($filters['period_to'])) {
            $query->forPeriod($filters['period_from'], $filters['period_to']);
        }

        if (!empty($filters['declaration_type'])) {
            $query->where('declaration_type', $filters['declaration_type']);
        }

        return $query->paginate($perPage);
    }
}
