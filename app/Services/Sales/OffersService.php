<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\ProductBundle;
use App\Models\Sales\SeasonalCampaign;
use Illuminate\Support\Facades\DB;

class OffersService
{
    public function getBundles(int $organizationId): mixed
    {
        return ProductBundle::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with('items.product')
            ->orderBy('display_order')
            ->get();
    }

    public function createBundle(array $data): ProductBundle
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $bundle = ProductBundle::create($data);

            foreach ($items as $item) {
                $bundle->items()->create($item);
            }

            $bundle->update([
                'original_total' => $bundle->items->reduce(function (string $carry, $item): string {
                    return bcadd($carry, bcmul((string) $item->original_price, (string) $item->quantity, 4), 4);
                }, '0.0000'),
            ]);

            if ($bundle->bundle_price) {
                $bundle->update([
                    'savings_amount' => $bundle->original_total - $bundle->bundle_price,
                ]);
            }

            return $bundle->load('items');
        });
    }

    public function getCampaigns(int $organizationId): mixed
    {
        return SeasonalCampaign::where('organization_id', $organizationId)
            ->orderByDesc('starts_at')
            ->get();
    }

    public function getActiveCampaigns(int $organizationId): mixed
    {
        return SeasonalCampaign::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('priority', 'desc')
            ->get();
    }

    public function createCampaign(array $data): SeasonalCampaign
    {
        return SeasonalCampaign::create($data);
    }

    public function calculateBundlePrice(int $bundleId, array $selectedItemIds = []): array
    {
        $bundle = ProductBundle::with('items')->findOrFail($bundleId);

        $items = $bundle->items;
        if (!empty($selectedItemIds)) {
            $items = $items->filter(fn ($item) => !$item->is_optional || in_array($item->id, $selectedItemIds));
        } else {
            $items = $items->filter(fn ($item) => !$item->is_optional || $item->is_default_selected);
        }

        $originalTotal = $items->reduce(function (string $carry, $item): string {
            return bcadd($carry, bcmul((string) $item->original_price, (string) $item->quantity, 4), 4);
        }, '0.0000');

        $bundlePrice = match ($bundle->pricing_type) {
            'fixed' => (string) $bundle->bundle_price,
            'percentage_discount' => (function () use ($originalTotal, $bundle): string {
                $discountFactor = bcsub('1', bcdiv((string) $bundle->discount_percent, '100', 6), 6);
                return bcmul($originalTotal, $discountFactor, 4);
            })(),
            default => $originalTotal,
        };

        return [
            'original_total' => round((float) $originalTotal, 2),
            'bundle_price' => round((float) $bundlePrice, 2),
            'total_price' => round((float) $bundlePrice, 2),
            'savings' => round((float) bcsub($originalTotal, $bundlePrice, 4), 2),
            'items_count' => $items->count(),
        ];
    }
}
