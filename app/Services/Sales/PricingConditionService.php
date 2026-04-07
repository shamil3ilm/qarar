<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\PricingConditionRecord;
use App\Models\Sales\PricingConditionType;
use App\Models\Sales\PricingProcedure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PricingConditionService
{
    /**
     * Resolve the effective price for a line item by applying pricing procedure steps.
     *
     * Returns the calculated net price and a breakdown of each condition that was applied.
     *
     * @return array{net_price: float, currency: string, breakdown: list<array{condition_code: string, condition_class: string, calculation_type: string, rate: float, amount: float}>}
     */
    public function resolvePriceForLine(
        int $productId,
        int $contactId,
        float $quantity,
        string $currency
    ): array {
        $today = now()->toDateString();
        $organizationId = auth()->user()->organization_id;

        // Load active condition types ordered by procedure step
        $conditionTypes = PricingConditionType::where('organization_id', $organizationId)
            ->ordered()
            ->get();

        $basePrice = 0.0;
        $breakdown = [];

        foreach ($conditionTypes as $type) {
            $record = $this->findBestRecord($type->id, $productId, $contactId, $quantity, $today, $currency);

            if ($record === null) {
                if ($type->is_mandatory) {
                    // Mandatory condition not found — price stays at 0 for this step
                    continue;
                }
                continue;
            }

            $amount = $this->calculateAmount($type->calculation_type, $type->condition_class, (float) $record->rate, $basePrice, $quantity);

            $breakdown[] = [
                'condition_code' => $type->code,
                'condition_class' => $type->condition_class,
                'calculation_type' => $type->calculation_type,
                'rate' => (float) $record->rate,
                'amount' => $amount,
            ];

            $basePrice = $this->applyToBase($type->condition_class, $basePrice, $amount);
        }

        return [
            'net_price' => round($basePrice, 4),
            'currency' => $currency,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Find the most specific applicable condition record using SAP key combination priority:
     * customer_material > customer > material > price_list > all
     */
    private function findBestRecord(
        int $conditionTypeId,
        int $productId,
        int $customerId,
        float $quantity,
        string $date,
        string $currency
    ): ?PricingConditionRecord {
        $baseQuery = PricingConditionRecord::where('condition_type_id', $conditionTypeId)
            ->active()
            ->validOn($date)
            ->forQuantity($quantity)
            ->where('currency_code', $currency);

        $priorities = [
            'customer_material' => fn($q) => $q->where('key_combination', 'customer_material')
                ->where('customer_id', $customerId)
                ->where('product_id', $productId),
            'customer' => fn($q) => $q->where('key_combination', 'customer')
                ->where('customer_id', $customerId),
            'material' => fn($q) => $q->where('key_combination', 'material')
                ->where('product_id', $productId),
            'all' => fn($q) => $q->where('key_combination', 'all'),
        ];

        foreach ($priorities as $scope) {
            $record = $scope((clone $baseQuery))->first();
            if ($record !== null) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Calculate the monetary amount for a condition given type, base price and quantity.
     */
    private function calculateAmount(
        string $calculationType,
        string $conditionClass,
        float $rate,
        float $basePrice,
        float $quantity
    ): float {
        return match ($calculationType) {
            'fixed' => $rate,
            'percentage' => round($basePrice * ($rate / 100), 4),
            'quantity' => round($rate * $quantity, 4),
            default => $rate,
        };
    }

    /**
     * Apply a condition amount to the running base price based on its class.
     */
    private function applyToBase(string $conditionClass, float $basePrice, float $amount): float
    {
        return match ($conditionClass) {
            'price' => $amount,
            'discount' => $basePrice - $amount,
            'surcharge', 'freight' => $basePrice + $amount,
            'tax' => $basePrice, // Tax is informational; does not alter net price
            default => $basePrice,
        };
    }

    /**
     * Create a pricing procedure.
     */
    public function storeProcedure(array $data): PricingProcedure
    {
        return DB::transaction(function () use ($data): PricingProcedure {
            $data['organization_id'] = auth()->user()->organization_id;

            // Only one default per organization
            if (!empty($data['is_default'])) {
                PricingProcedure::where('organization_id', $data['organization_id'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return PricingProcedure::create($data);
        });
    }

    /**
     * Update an existing pricing procedure.
     */
    public function updateProcedure(PricingProcedure $procedure, array $data): PricingProcedure
    {
        return DB::transaction(function () use ($procedure, $data): PricingProcedure {
            if (!empty($data['is_default'])) {
                PricingProcedure::where('organization_id', $procedure->organization_id)
                    ->where('id', '!=', $procedure->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $procedure->update($data);

            return $procedure->fresh();
        });
    }

    /**
     * Create a condition type.
     */
    public function storeConditionType(array $data): PricingConditionType
    {
        $data['organization_id'] = auth()->user()->organization_id;

        return PricingConditionType::create($data);
    }

    /**
     * Update an existing condition type.
     */
    public function updateConditionType(PricingConditionType $type, array $data): PricingConditionType
    {
        $type->update($data);

        return $type->fresh();
    }

    /**
     * Create a condition record with validity dates.
     */
    public function storeConditionRecord(array $data): PricingConditionRecord
    {
        return PricingConditionRecord::create($data);
    }

    /**
     * Update an existing condition record.
     */
    public function updateConditionRecord(PricingConditionRecord $record, array $data): PricingConditionRecord
    {
        $record->update($data);

        return $record->fresh();
    }

    /**
     * Paginated listing of condition records with optional filters.
     */
    public function indexConditionRecords(array $filters): LengthAwarePaginator
    {
        $organizationId = auth()->user()->organization_id;

        return PricingConditionRecord::whereHas('conditionType', function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId);
        })
            ->with('conditionType')
            ->when(isset($filters['condition_type_id']), fn($q) => $q->where('condition_type_id', $filters['condition_type_id']))
            ->when(isset($filters['product_id']), fn($q) => $q->where('product_id', $filters['product_id']))
            ->when(isset($filters['customer_id']), fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['key_combination']), fn($q) => $q->where('key_combination', $filters['key_combination']))
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when(isset($filters['currency_code']), fn($q) => $q->where('currency_code', $filters['currency_code']))
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }
}
