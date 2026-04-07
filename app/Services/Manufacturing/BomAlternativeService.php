<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\BomAlternative;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BomAlternativeService
{
    public function list(int $productId, array $filters = []): Collection
    {
        return BomAlternative::with(['product', 'bomTemplate'])
            ->forProduct($productId)
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['usage_type']), fn($q) => $q->where('usage_type', $filters['usage_type']))
            ->when(isset($filters['valid_on']), fn($q) => $q->validOn($filters['valid_on']))
            ->orderBy('alternative_number')
            ->get();
    }

    public function create(array $data): BomAlternative
    {
        return DB::transaction(function () use ($data): BomAlternative {
            if (!empty($data['is_default'])) {
                BomAlternative::forProduct($data['product_id'])
                    ->where('usage_type', $data['usage_type'] ?? BomAlternative::USAGE_PRODUCTION)
                    ->update(['is_default' => false]);
            }

            return BomAlternative::create($data);
        });
    }

    public function update(BomAlternative $alternative, array $data): BomAlternative
    {
        return DB::transaction(function () use ($alternative, $data): BomAlternative {
            if (!empty($data['is_default'])) {
                BomAlternative::forProduct($alternative->product_id)
                    ->where('usage_type', $data['usage_type'] ?? $alternative->usage_type)
                    ->where('id', '!=', $alternative->id)
                    ->update(['is_default' => false]);
            }

            $alternative->update($data);

            return $alternative->fresh();
        });
    }

    public function setDefault(BomAlternative $alternative): void
    {
        DB::transaction(function () use ($alternative): void {
            BomAlternative::forProduct($alternative->product_id)
                ->where('usage_type', $alternative->usage_type)
                ->where('id', '!=', $alternative->id)
                ->update(['is_default' => false]);

            $alternative->update(['is_default' => true]);
        });
    }

    public function determineAlternative(
        int $productId,
        float $quantity,
        ?string $date = null,
        string $usageType = 'production'
    ): ?BomAlternative {
        $date = $date ?? now()->toDateString();

        $alternatives = BomAlternative::forProduct($productId)
            ->active()
            ->validOn($date)
            ->where('usage_type', $usageType)
            ->with('bomTemplate')
            ->get()
            ->filter(fn(BomAlternative $alt): bool => $alt->isLotSizeCompatible($quantity));

        if ($alternatives->isEmpty()) {
            return null;
        }

        $default = $alternatives->firstWhere('is_default', true);

        return $default ?? $alternatives->first();
    }
}
