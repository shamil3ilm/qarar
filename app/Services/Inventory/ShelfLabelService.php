<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ShelfLabel;
use Illuminate\Support\Facades\DB;

class ShelfLabelService
{
    public function __construct() {}

    /**
     * Create a shelf label.
     */
    public function create(array $data): ShelfLabel
    {
        return DB::transaction(function () use ($data) {
            $product = Product::findOrFail($data['product_id']);

            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;
            $data['product_name'] = $data['product_name'] ?? $product->name;
            $data['sku'] = $data['sku'] ?? $product->sku;
            $data['price'] = $data['price'] ?? $product->selling_price;
            $data['barcode_value'] = $data['barcode_value'] ?? $product->barcode;
            $data['needs_reprint'] = true;

            return ShelfLabel::create($data);
        });
    }

    /**
     * Bulk create shelf labels for multiple products.
     */
    public function bulkCreate(array $items, int $branchId, string $currencyCode = 'SAR'): array
    {
        $results = [];

        foreach ($items as $item) {
            try {
                $item['branch_id'] = $branchId;
                $item['currency_code'] = $item['currency_code'] ?? $currencyCode;

                $results[] = [
                    'product_id' => $item['product_id'],
                    'label' => $this->create($item),
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'product_id' => $item['product_id'],
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        return $results;
    }

    /**
     * Mark labels for reprint.
     */
    public function markForReprint(array $labelIds): int
    {
        return ShelfLabel::whereIn('id', $labelIds)
            ->where('is_digital', false)
            ->update(['needs_reprint' => true]);
    }

    /**
     * Sync a digital (ESL) label.
     */
    public function syncDigitalLabel(ShelfLabel $label): ShelfLabel
    {
        if (!$label->isDigital()) {
            throw new \InvalidArgumentException('Label is not a digital/ESL label.');
        }

        return DB::transaction(function () use ($label) {
            // Refresh data from the product
            $product = $label->product;

            $label->update([
                'product_name' => $product->name,
                'sku' => $product->sku,
                'price' => $product->selling_price,
                'barcode_value' => $product->barcode,
                'last_synced_at' => now(),
            ]);

            return $label->fresh();
        });
    }
}
